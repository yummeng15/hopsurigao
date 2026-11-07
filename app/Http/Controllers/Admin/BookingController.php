<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreOnsiteBookingRequest;
use App\Models\Booking;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Role;
use App\Models\TourDate;
use App\Models\User;
use App\Notifications\BookingCancelledNotification;
use App\Notifications\BookingConfirmedNotification;
use App\Notifications\BookingCreatedNotification;
use App\Notifications\PaymentConfirmedNotification;
use App\Services\FileUploadService;
use App\Services\UploadPath;
use App\Services\WeatherAdvisoryService;
use App\Support\CsvDownload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BookingController extends Controller
{
    public function __construct(
        private FileUploadService $files,
        private WeatherAdvisoryService $weatherAdvisory,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Index
    |--------------------------------------------------------------------------
    */

    public function index(): Response
    {
        return Inertia::render(
            'admin/bookings/Index'
        );
    }

    /**
     * Month view of upcoming booked tour days, in date order.
     */
    public function calendar(): Response
    {
        return Inertia::render('admin/bookings/Calendar', [
            'today' => now()->toDateString(),
            'days' => $this->upcomingBookedDays(),
        ]);
    }

    /**
     * Form for a guest who is booking and paying cash at the counter.
     *
     * Packages load first; tour dates load on demand so the page stays fast
     * even when every package has 60 daily dates.
     */
    public function create(): Response
    {
        $packages = Package::query()
            ->where('status', 'active')
            ->whereHas(
                'tourDates',
                fn ($query) => $query->whereDate('tour_date', '>=', now()->toDateString()),
            )
            ->orderBy('package_name')
            ->get(['id', 'package_name', 'destination', 'price', 'under_maintenance'])
            ->map(fn (Package $package) => [
                'id' => $package->id,
                'package_name' => $package->package_name,
                'destination' => $package->destination,
                'price' => (float) $package->price,
                'under_maintenance' => (bool) $package->under_maintenance,
                'tour_dates' => [],
            ])
            ->values();

        return Inertia::render('admin/bookings/Create', [
            'packages' => $packages,
        ]);
    }

    /**
     * Tour dates + pickups for one package on the on-site booking form.
     */
    public function onsiteDates(Package $package): JsonResponse
    {
        abort_unless($package->status === 'active', 404);

        $bookedGuests = Booking::query()
            ->selectRaw('tour_date_id, SUM(number_of_guests) as guests')
            ->whereIn('booking_status', ['pending', 'confirmed'])
            ->whereIn(
                'tour_date_id',
                TourDate::query()
                    ->where('package_id', $package->id)
                    ->whereDate('tour_date', '>=', now()->toDateString())
                    ->whereDate('tour_date', '<=', now()->addDays(29)->toDateString())
                    ->select('id'),
            )
            ->groupBy('tour_date_id')
            ->pluck('guests', 'tour_date_id');

        $tourDates = TourDate::query()
            ->where('package_id', $package->id)
            ->whereDate('tour_date', '>=', now()->toDateString())
            ->whereDate('tour_date', '<=', now()->addDays(29)->toDateString())
            ->with([
                'pickupSchedules.pickupLocation:id,name',
                'weatherForecast:id,tour_date_id,advisory_level',
            ])
            ->orderBy('tour_date')
            ->get()
            ->map(function (TourDate $tourDate) use ($bookedGuests, $package) {
                $remaining = max(0, $tourDate->capacity - (int) ($bookedGuests[$tourDate->id] ?? 0));
                $closure = $this->weatherAdvisory->bookingClosure(
                    (bool) $tourDate->travel_closed,
                    $tourDate->weatherForecast?->advisory_level,
                );

                if ($package->under_maintenance) {
                    $closure ??= 'This boat is under maintenance. Booking is not allowed.';
                }

                return [
                    'id' => $tourDate->id,
                    'tour_date' => $tourDate->tour_date->toDateString(),
                    'remaining_capacity' => $remaining,
                    'booking_closed' => $closure !== null,
                    'booking_closed_reason' => $closure,
                    'pickup_schedules' => $tourDate->pickupSchedules
                        ->map(fn ($schedule) => [
                            'id' => $schedule->id,
                            'pickup_time' => $schedule->pickup_time?->format('g:i A'),
                            'location_name' => $schedule->pickupLocation?->name ?? 'Pickup point',
                        ])
                        ->values(),
                ];
            })
            ->filter(fn (array $tourDate) => $tourDate['remaining_capacity'] > 0 && $tourDate['pickup_schedules']->isNotEmpty())
            ->values();

        return response()->json([
            'tour_dates' => $tourDates,
        ]);
    }

    /**
     * Save a walk-in booking that was paid in cash at the counter.
     */
    public function store(StoreOnsiteBookingRequest $request): RedirectResponse
    {
        $booking = DB::transaction(function () use ($request) {
            $tourDate = TourDate::query()
                ->whereKey($request->integer('tour_date_id'))
                ->lockForUpdate()
                ->with('package')
                ->firstOrFail();

            abort_unless($tourDate->package->status === 'active', 404);
            abort_unless($tourDate->tour_date->toDateString() >= now()->toDateString(), 422);

            $pickupBelongsToDate = $tourDate->pickupSchedules()
                ->whereKey($request->integer('pickup_schedule_id'))
                ->exists();

            abort_unless($pickupBelongsToDate, 422);

            $tourDate->loadMissing('weatherForecast');

            $closure = $this->weatherAdvisory->bookingClosure(
                (bool) $tourDate->travel_closed,
                $tourDate->weatherForecast?->advisory_level,
            );

            if ($closure !== null) {
                throw ValidationException::withMessages([
                    'tour_date_id' => $closure,
                ]);
            }

            if ($tourDate->package->under_maintenance) {
                throw ValidationException::withMessages([
                    'tour_date_id' => 'This boat is under maintenance. Booking is not allowed.',
                ]);
            }

            $bookedGuests = $tourDate->bookings()
                ->whereIn('booking_status', ['pending', 'confirmed'])
                ->sum('number_of_guests');

            $remaining = max(0, $tourDate->capacity - $bookedGuests);

            if ($request->integer('number_of_guests') > $remaining) {
                throw ValidationException::withMessages([
                    'number_of_guests' => 'There are only '.$remaining.' guest(s) remaining.',
                ]);
            }

            $customer = $this->customerForOnsiteBooking($request);

            $alreadyBooked = Booking::query()
                ->where('user_id', $customer->id)
                ->where('tour_date_id', $tourDate->id)
                ->whereIn('booking_status', ['pending', 'confirmed'])
                ->exists();

            if ($alreadyBooked) {
                throw ValidationException::withMessages([
                    'email' => 'This guest already has an active booking for this tour date.',
                ]);
            }

            $booking = Booking::query()->create([
                'user_id' => $customer->id,
                'tour_date_id' => $tourDate->id,
                'pickup_schedule_id' => $request->integer('pickup_schedule_id'),
                'number_of_guests' => $request->integer('number_of_guests'),
                'phone_number' => $request->string('phone_number')->toString(),
                'nationality' => $request->string('nationality')->toString(),
                'special_request' => $request->input('special_request'),
                'booking_status' => 'confirmed',
            ]);

            Payment::query()->create([
                'booking_id' => $booking->id,
                'amount' => round((float) $tourDate->package->price * $request->integer('number_of_guests'), 2),
                'payment_method' => 'Cash',
                'payment_status' => 'paid',
                'notes' => 'Cash collected on site.',
                'paid_at' => now(),
            ]);

            return $booking;
        });

        $operators = User::query()
            ->whereHas('role', fn ($query) => $query->whereIn('name', ['Admin', 'Superadmin']))
            ->get();

        if ($operators->isNotEmpty()) {
            Notification::send($operators, new BookingCreatedNotification($booking));
        }

        $booking->user?->notify(new BookingConfirmedNotification($booking));

        $payment = $booking->payments()->first();

        if ($payment !== null) {
            $booking->user?->notify(new PaymentConfirmedNotification($payment));
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'On-site booking saved. Cash is marked as paid.',
        ]);

        return redirect()->route('admin.bookings.show', $booking);
    }

    /**
     * Record money collected after a downpayment and reduce the remaining balance.
     */
    public function recordBalance(Request $request, Booking $booking): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
        ]);

        $booking->load(['payments', 'tourDate.package']);

        if ((int) round($booking->amountReceived() * 100) <= 0) {
            return back()->withErrors([
                'amount' => 'Record this only after a downpayment has been received.',
            ]);
        }

        $amountCents = (int) round(((float) $validated['amount']) * 100);
        $remainingCents = (int) round($booking->remainingBalance() * 100);

        if ($remainingCents <= 0) {
            return back()->withErrors([
                'amount' => 'This booking has no remaining balance.',
            ]);
        }

        if ($amountCents > $remainingCents) {
            return back()->withErrors([
                'amount' => 'That amount is higher than the remaining balance.',
            ]);
        }

        Payment::query()->create([
            'booking_id' => $booking->id,
            'amount' => $amountCents / 100,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'notes' => 'Balance collected. This payment updates the remaining balance.',
            'paid_at' => now(),
        ]);

        $booking->unsetRelation('payments');

        if ((int) round($booking->remainingBalance() * 100) === 0) {
            $booking->payments()
                ->where('payment_status', 'partial')
                ->update([
                    'payment_status' => 'paid',
                ]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Received payment recorded. The remaining balance is updated.',
        ]);

        return back();
    }

    /*
    |--------------------------------------------------------------------------
    | DataTable
    |--------------------------------------------------------------------------
    */

    public function getData(Request $request): JsonResponse
    {
        $query = Booking::query()
            ->where('is_archived', false)
            ->with([
                'user',
                'tourDate.package',
                'pickupSchedule.pickupLocation',
                'payments' => fn ($query) => $query->latest('id'),
            ]);

        /*
        |--------------------------------------------------------------------------
        | Search
        |--------------------------------------------------------------------------
        */

        if ($search = $request->input('search')) {

            $query->where(function ($query) use ($search) {

                $query
                    ->where(
                        'id',
                        'like',
                        "%{$search}%"
                    )

                    ->orWhere(
                        'phone_number',
                        'like',
                        "%{$search}%"
                    )

                    ->orWhere(
                        'nationality',
                        'like',
                        "%{$search}%"
                    )

                    ->orWhere(
                        'booking_status',
                        'like',
                        "%{$search}%"
                    )

                    ->orWhereHas(
                        'user',
                        function ($query) use ($search) {

                            $query
                                ->where(
                                    'name',
                                    'like',
                                    "%{$search}%"
                                )

                                ->orWhere(
                                    'email',
                                    'like',
                                    "%{$search}%"
                                );
                        }
                    )

                    ->orWhereHas(
                        'tourDate.package',
                        function ($query) use ($search) {

                            $query
                                ->where(
                                    'package_name',
                                    'like',
                                    "%{$search}%"
                                )

                                ->orWhere(
                                    'destination',
                                    'like',
                                    "%{$search}%"
                                );
                        }
                    );
            });
        }

        /*
        |--------------------------------------------------------------------------
        | Status Filter
        |--------------------------------------------------------------------------
        */

        if ($status = $request->input('status')) {

            $query->where(
                'booking_status',
                $status
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Sorting
        |--------------------------------------------------------------------------
        */

        $allowedSorts = [
            'id',
            'number_of_guests',
            'nationality',
            'booking_status',
            'created_at',
        ];

        $sort = $request->input('sort');

        if (
            $sort &&
            in_array(
                $sort,
                $allowedSorts,
                true
            )
        ) {

            $order = $request->input(
                'order',
                'asc'
            );

            if (
                ! in_array(
                    $order,
                    ['asc', 'desc'],
                    true
                )
            ) {
                $order = 'asc';
            }

            $query->orderBy(
                $sort,
                $order
            );
        } else {

            $query->latest();
        }

        /*
        |--------------------------------------------------------------------------
        | Pagination
        |--------------------------------------------------------------------------
        */

        $perPage = (int) $request->input(
            'per_page',
            10
        );

        $perPage = min(
            max($perPage, 1),
            100
        );

        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        return response()->json(

            $query
                ->paginate($perPage)

                ->through(
                    function (Booking $booking) {

                        return [

                            'id' => $booking->id,

                            /*
                            |--------------------------------------------------------------------------
                            | Customer
                            |--------------------------------------------------------------------------
                            */

                            'user_name' => $booking
                                ->user
                                ?->name,

                            'user_email' => $booking
                                ->user
                                ?->email,

                            /*
                            |--------------------------------------------------------------------------
                            | Package
                            |--------------------------------------------------------------------------
                            */

                            'package_name' => $booking
                                ->tourDate
                                ?->package
                                ?->package_name,

                            'destination' => $booking
                                ->tourDate
                                ?->package
                                ?->destination,

                            /*
                            |--------------------------------------------------------------------------
                            | Tour
                            |--------------------------------------------------------------------------
                            */

                            'tour_date' => $booking
                                ->tourDate
                                ?->tour_date,

                            /*
                            |--------------------------------------------------------------------------
                            | Pickup
                            |--------------------------------------------------------------------------
                            */

                            'pickup_location' => $booking
                                ->pickupSchedule
                                ?->pickupLocation
                                ?->name,

                            'pickup_time' => $booking
                                ->pickupSchedule
                                ?->pickup_time,

                            /*
                            |--------------------------------------------------------------------------
                            | Booking
                            |--------------------------------------------------------------------------
                            */

                            'number_of_guests' => $booking
                                ->number_of_guests,

                            'phone_number' => $booking
                                ->phone_number,

                            'nationality' => $booking
                                ->nationality,

                            'booking_status' => $booking
                                ->booking_status,

                            'payment_id' => $booking
                                ->payments
                                ->first()
                                ?->id,

                            'payment_status' => $booking
                                ->payments
                                ->first()
                                ?->payment_status,

                            'payment_method' => $booking
                                ->payments
                                ->first()
                                ?->payment_method,

                            'amount_received' => $booking->amountReceived(),

                            'remaining_balance' => $booking->remainingBalance(),

                            'has_payment_proof' => $this->files->files(
                                UploadPath::payment($booking->id)
                            ) !== [],

                            /*
                            |--------------------------------------------------------------------------
                            | Meta
                            |--------------------------------------------------------------------------
                            */

                            'created_at' => $booking
                                ->created_at,
                        ];
                    }
                )
        );
    }

    public function export(): StreamedResponse
    {
        $bookings = Booking::query()
            ->where('is_archived', false)
            ->with([
                'user',
                'tourDate.package',
                'pickupSchedule.pickupLocation',
                'payments' => fn ($query) => $query->latest('id'),
            ])
            ->latest()
            ->get();

        $rows = $bookings->map(function (Booking $booking): array {
            $payment = $booking->payments->first();

            return [
                $booking->id,
                $booking->user?->name,
                $booking->user?->email,
                $booking->phone_number,
                $booking->nationality,
                $booking->tourDate?->package?->package_name,
                $booking->tourDate?->package?->destination,
                $booking->tourDate?->tour_date?->toDateString(),
                $booking->pickupSchedule?->pickupLocation?->name,
                $booking->pickupSchedule?->pickup_time,
                $booking->number_of_guests,
                $booking->booking_status,
                $payment?->payment_method,
                $payment?->payment_status,
                $booking->created_at?->toDateTimeString(),
            ];
        });

        return CsvDownload::stream(
            'bookings-'.now()->format('Y-m-d').'.csv',
            [
                'ID',
                'Customer',
                'Email',
                'Phone',
                'Nationality',
                'Package',
                'Destination',
                'Tour Date',
                'Pickup',
                'Pickup Time',
                'Guests',
                'Booking Status',
                'Payment Method',
                'Payment Status',
                'Booked At',
            ],
            $rows,
        );
    }

    public function show(
        Booking $booking
    ): Response {

        $booking->load([
            'user',
            'tourDate.package',
            'tourDate.times',
            'pickupSchedule.pickupLocation',
            'payments',
        ]);

        $package = $booking->tourDate?->package;

        /*
    |--------------------------------------------------------------------------
    | Package images come from the filesystem, not the database.
    |
    | $this->files->images() returns a plain PHP array of
    | ['name' => ..., 'path' => ..., 'url' => ...] items,
    | so we use array_column() (not ->pluck()) to extract urls.
    |--------------------------------------------------------------------------
    */
        $packagePayload = null;

        if ($package) {
            $packagePayload = [
                ...$package->toArray(),

                'image' => null,

                'image_urls' => array_column(
                    $this->files->images(
                        UploadPath::package($package->id)
                    ),
                    'url'
                ),
            ];
        }

        return Inertia::render(
            'admin/bookings/Show',
            [
                'booking' => [
                    'id' => $booking->id,

                    'user' => $booking->user,

                    'package' => $packagePayload,

                    'tour_date' => $booking->tourDate,

                    'pickup_schedule' => $booking->pickupSchedule,

                    'pickup_latitude' => $booking->pickup_latitude,

                    'pickup_longitude' => $booking->pickup_longitude,

                    'number_of_guests' => $booking->number_of_guests,

                    'phone_number' => $booking->phone_number,

                    'nationality' => $booking->nationality,

                    'special_request' => $booking->special_request,

                    'booking_status' => $booking->booking_status,

                    'qr_token' => $booking->qr_token,
                    'payments' => $booking->payments
                        ->map(function ($payment) use ($booking) {

                            $proofFiles = $this->files->filesWithMeta(
                                UploadPath::payment($booking->id)
                            );

                            $proof = $proofFiles[0] ?? null;

                            return [
                                'id' => $payment->id,

                                'amount' => $payment->amount,

                                'payment_method' => $payment->payment_method,

                                'payment_status' => $payment->payment_status,

                                'transaction_reference' => $payment->transaction_reference,

                                'proof_of_payment_url' => $proof['url'] ?? null,

                                'proof_of_payment_is_image' => $proof['is_image'] ?? null,

                                'notes' => $payment->notes,

                                'paid_at' => $payment->paid_at,

                                'created_at' => $payment->created_at,

                                'updated_at' => $payment->updated_at,
                            ];
                        })
                        ->values(),

                    'created_at' => $booking->created_at,

                    'updated_at' => $booking->updated_at,
                ],
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Edit
    |--------------------------------------------------------------------------
    */

    public function edit(
        Booking $booking
    ): Response {

        $booking->load([
            'user',
            'tourDate.package',
            'pickupSchedule.pickupLocation',
        ]);

        return Inertia::render(
            'admin/bookings/Edit',
            [
                'booking' => [

                    'id' => $booking->id,

                    'customer_name' => $booking
                        ->user
                        ?->name,

                    'customer_email' => $booking
                        ->user
                        ?->email,

                    'package_name' => $booking
                        ->tourDate
                        ?->package
                        ?->package_name,

                    'destination' => $booking
                        ->tourDate
                        ?->package
                        ?->destination,

                    'tour_date' => $booking
                        ->tourDate
                        ?->tour_date,

                    'pickup_location' => $booking
                        ->pickupSchedule
                        ?->pickupLocation
                        ?->name,

                    'number_of_guests' => $booking
                        ->number_of_guests,

                    'phone_number' => $booking
                        ->phone_number,

                    'nationality' => $booking
                        ->nationality,

                    'special_request' => $booking
                        ->special_request,

                    'booking_status' => $booking
                        ->booking_status,
                ],
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Update
    |--------------------------------------------------------------------------
    */

    public function update(
        Request $request,
        Booking $booking
    ): RedirectResponse {

        $data = $request->validate([

            'number_of_guests' => [
                'required',
                'integer',
                'min:1',
            ],

            'phone_number' => [
                'required',
                'string',
                'max:255',
            ],

            'nationality' => [
                'required',
                'string',
                'max:255',
            ],

            'special_request' => [
                'nullable',
                'string',
            ],

            'booking_status' => [
                'required',
                Rule::in([
                    'pending',
                    'confirmed',
                    'cancelled',
                    'completed',
                ]),
            ],

        ]);

        /*
        |--------------------------------------------------------------------------
        | Update Booking
        |--------------------------------------------------------------------------
        */

        if (
            $request->user()?->role?->name !== 'Admin'
            && $data['booking_status'] === 'confirmed'
            && $booking->booking_status !== 'confirmed'
        ) {
            abort(403, 'Only the boat operator can confirm a booking.');
        }

        $oldStatus = $booking->booking_status;
        $booking->update($data);

        // Notify tourist on status transition
        if ($oldStatus !== 'confirmed' && $booking->booking_status === 'confirmed') {
            $booking->user?->notify(new BookingConfirmedNotification($booking));
        } elseif ($oldStatus !== 'cancelled' && $booking->booking_status === 'cancelled') {
            $booking->user?->notify(new BookingCancelledNotification($booking, 'Operator'));
        }

        /*
        |--------------------------------------------------------------------------
        | Booking -> Payment Synchronization
        |--------------------------------------------------------------------------
        */

        $paymentStatus = match ($data['booking_status']) {

            'pending' => 'pending',

            'confirmed' => 'paid',

            'completed' => 'paid',

            'cancelled' => 'cancelled',

            default => 'pending',
        };

        /*
        |--------------------------------------------------------------------------
        | Update Related Payments
        |--------------------------------------------------------------------------
        |
        | IMPORTANT:
        |
        | When payment is no longer paid, paid_at MUST be cleared.
        |
        */

        $acceptsPayment = $request->user()?->role?->name === 'Superadmin';

        if ($booking->payments()->exists() && ($acceptsPayment || $paymentStatus === 'cancelled')) {

            $payments = $booking->payments();

            if ($paymentStatus === 'paid') {
                $payments->where('payment_status', '!=', 'partial');
            }

            $payments->update([

                'payment_status' => $paymentStatus,

                'paid_at' => $paymentStatus === 'paid'
                    ? now()
                    : null,
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Flash Message
        |--------------------------------------------------------------------------
        */

        Inertia::flash('toast', [

            'type' => 'success',

            'message' => 'Booking and payment status updated successfully.',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Redirect
        |--------------------------------------------------------------------------
        */

        return redirect()->route(
            'admin.bookings.index'
        );
    }

    /**
     * The administrator accepts a submitted payment and confirms the booking.
     */
    public function confirmPayment(Request $request, Booking $booking): RedirectResponse
    {
        if ($request->user()?->role?->name !== 'Superadmin') {
            abort(403, 'Only the administrator can confirm a payment.');
        }

        $payment = $booking->payments()->latest('id')->first();

        if ($payment === null) {
            return back()->withErrors([
                'payment' => 'This booking does not have a payment to confirm.',
            ]);
        }

        if ($payment->payment_status === 'paid') {
            return back()->withErrors([
                'payment' => 'This payment is already confirmed.',
            ]);
        }

        $payment->update([
            'payment_status' => 'paid',
            'paid_at' => now(),
        ]);

        $wasConfirmed = $booking->booking_status === 'confirmed';

        if ($booking->booking_status === 'pending') {
            $booking->update([
                'booking_status' => 'confirmed',
            ]);
        }

        $booking->user?->notify(new PaymentConfirmedNotification($payment));

        if (! $wasConfirmed && $booking->fresh()->booking_status === 'confirmed') {
            $booking->user?->notify(new BookingConfirmedNotification($booking));
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Payment confirmed. The booking is now confirmed.',
        ]);

        return back();
    }

    /*
    |--------------------------------------------------------------------------
    | Destroy
    |--------------------------------------------------------------------------
    */

    public function destroy(
        Booking $booking
    ): RedirectResponse {

        if (in_array($booking->booking_status, ['confirmed', 'completed'])) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'Confirmed or completed bookings cannot be deleted.',
            ]);

            return redirect()->back();
        }

        $this->files->deleteDirectory(
            UploadPath::payment($booking->id)
        );

        $booking->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Booking deleted successfully.',
        ]);

        return redirect()->route(
            'admin.bookings.index'
        );
    }

    /**
     * Active bookings from today onward, grouped by tour date.
     *
     * @return array<int, array{
     *     date: string,
     *     bookings_count: int,
     *     guests: int,
     *     bookings: array<int, array{
     *         id: int,
     *         customer: string|null,
     *         package_name: string|null,
     *         destination: string|null,
     *         guests: int,
     *         status: string,
     *         pickup: string|null,
     *         phone: string
     *     }>
     * }>
     */
    private function upcomingBookedDays(): array
    {
        $bookings = Booking::query()
            ->where('is_archived', false)
            ->whereIn('booking_status', ['pending', 'confirmed'])
            ->whereHas('tourDate', function ($query) {
                $query->whereDate('tour_date', '>=', now()->toDateString());
            })
            ->with([
                'user',
                'tourDate.package',
                'pickupSchedule.pickupLocation',
            ])
            ->get()
            ->sortBy(fn (Booking $booking): string => sprintf(
                '%s-%010d',
                $booking->tourDate->tour_date->toDateString(),
                $booking->id,
            ));

        return $bookings
            ->groupBy(
                fn (Booking $booking): string => $booking->tourDate->tour_date->toDateString()
            )
            ->map(function ($dayBookings, string $date): array {
                return [
                    'date' => $date,
                    'bookings_count' => $dayBookings->count(),
                    'guests' => (int) $dayBookings->sum('number_of_guests'),
                    'bookings' => $dayBookings->map(function (Booking $booking): array {
                        return [
                            'id' => $booking->id,
                            'customer' => $booking->user?->name,
                            'package_name' => $booking->tourDate?->package?->package_name,
                            'destination' => $booking->tourDate?->package?->destination,
                            'guests' => $booking->number_of_guests,
                            'status' => $booking->booking_status,
                            'pickup' => $booking->pickupSchedule?->pickupLocation?->name,
                            'phone' => $booking->phone_number,
                        ];
                    })->values()->all(),
                ];
            })
            ->values()
            ->all();
    }

    private function customerForOnsiteBooking(StoreOnsiteBookingRequest $request): User
    {
        $email = strtolower(trim((string) $request->input('email')));
        $touristRole = Role::query()->where('name', 'User')->firstOrFail();

        if ($email !== '') {
            $existing = User::query()->where('email', $email)->first();

            if ($existing !== null && $existing->role?->name !== 'User') {
                throw ValidationException::withMessages([
                    'email' => 'This email belongs to a staff account. Use the guest\'s own email, or leave it blank.',
                ]);
            }

            if ($existing !== null) {
                return $existing;
            }

            return User::query()->create([
                'name' => $request->string('customer_name')->toString(),
                'email' => $email,
                'password' => Str::password(),
                'role_id' => $touristRole->id,
            ]);
        }

        $phone = preg_replace('/\D+/', '', $request->string('phone_number')->toString()) ?: 'guest';

        return User::query()->create([
            'name' => $request->string('customer_name')->toString(),
            'email' => 'onsite.'.$phone.'.'.Str::lower(Str::random(8)).'@hopsurigao.local',
            'password' => Str::password(),
            'role_id' => $touristRole->id,
        ]);
    }
}
