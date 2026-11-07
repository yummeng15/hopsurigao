<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreBookingRequest;
use App\Models\Booking;
use App\Models\BookingCaseRequest;
use App\Models\Package;
use App\Models\Payment;
use App\Models\RefundRequest;
use App\Models\TourDate;
use App\Models\User;
use App\Notifications\BookingCancelledNotification;
use App\Notifications\BookingCreatedNotification;
use App\Services\FileUploadService;
use App\Services\PayMongoCheckoutService;
use App\Services\UploadPath;
use App\Services\WeatherAdvisoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class BookingController extends Controller
{
    public function __construct(
        private FileUploadService $files,
        private PayMongoCheckoutService $paymongo,
        private WeatherAdvisoryService $weatherAdvisory,
    ) {}

    /**
     * Booking list (Active: Pending & Confirmed).
     */
    public function index(): Response
    {
        $bookings = Booking::query()
            ->where('user_id', auth()->id())
            ->whereIn('booking_status', ['pending', 'confirmed'])
            ->where('is_archived', false)
            ->with([
                'tourDate.package',
                'tourDate.times',
                'tourDate.weatherForecast',
                'pickupSchedule.pickupLocation',
                'payments',
            ])
            ->latest()
            ->get();

        return Inertia::render(
            'user/bookings/Index',
            [
                'bookings' => $bookings,
            ]
        );
    }

    /**
     * History list (Completed & Cancelled bookings archive).
     */
    public function history(): Response
    {
        $bookings = Booking::query()
            ->where('user_id', auth()->id())
            ->whereIn('booking_status', ['completed', 'cancelled'])
            ->where('is_archived', false)
            ->with([
                'tourDate.package',
                'tourDate.times',
                'tourDate.weatherForecast',
                'pickupSchedule.pickupLocation',
                'payments',
            ])
            ->latest()
            ->get();

        return Inertia::render(
            'user/history/Index',
            [
                'bookings' => $bookings,
            ]
        );
    }

    /**
     * Show booking form.
     */
    public function create(Request $request): Response
    {
        $tourDateId = $request->integer('tour_date');

        abort_unless($tourDateId, 404);

        $tourDate = TourDate::query()
            ->whereDate(
                'tour_date',
                '>=',
                now()->toDateString()
            )
            ->with([
                'package',
                'times',
                'weatherForecast',
                'pickupSchedules.pickupLocation',
            ])
            ->findOrFail($tourDateId);

        abort_unless(
            $tourDate->package->status === 'active',
            404
        );

        $bookedGuests = $tourDate
            ->bookings()
            ->whereIn('booking_status', [
                'pending',
                'confirmed',
            ])
            ->sum('number_of_guests');

        $remainingCapacity = max(
            0,
            $tourDate->capacity - $bookedGuests
        );

        $closure = $this->weatherAdvisory->bookingClosure(
            (bool) $tourDate->travel_closed,
            $tourDate->weatherForecast?->advisory_level,
        );

        if ($tourDate->package->under_maintenance) {
            $closure ??= 'This boat is under maintenance. Booking is not allowed.';
        }

        return Inertia::render(
            'user/bookings/Create',
            [
                'tourDate' => [
                    'id' => $tourDate->id,

                    'tour_date' => $tourDate->tour_date->toDateString(),

                    'capacity' => $tourDate->capacity,

                    'booked_guests' => $bookedGuests,

                    'remaining_capacity' => $remainingCapacity,

                    'weather_forecast' => $tourDate->weatherForecast,

                    'booking_closed' => $closure !== null,

                    'booking_closed_reason' => $closure,

                    'package' => $this->packageRoutePayload($tourDate->package),

                    'times' => $tourDate->times,

                    'pickup_schedules' => $tourDate->pickupSchedules,
                ],
            ]
        );
    }

    /**
     * Store booking.
     *
     * Creates one active booking per user/tour date
     * and safely checks tour capacity.
     */
    public function store(
        StoreBookingRequest $request
    ): RedirectResponse {

        $booking = DB::transaction(function () use ($request) {

            /*
         * Lock the tour date row while checking
         * capacity.
         *
         * This prevents two simultaneous requests
         * from taking the same remaining seats.
         */
            $tourDate = TourDate::query()
                ->whereKey($request->tour_date_id)
                ->lockForUpdate()
                ->with('package')
                ->firstOrFail();

            /*
         * Package must still be active.
         */
            abort_unless(
                $tourDate->package->status === 'active',
                404
            );

            /*
         * Prevent duplicate active booking.
         *
         * A user can have only ONE pending/confirmed
         * booking for the same tour date.
         */
            $duplicateBooking = Booking::query()
                ->where('user_id', auth()->id())
                ->where('tour_date_id', $tourDate->id)
                ->whereIn('booking_status', [
                    'pending',
                    'confirmed',
                ])
                ->exists();

            if ($duplicateBooking) {
                throw ValidationException::withMessages([
                    'tour_date_id' => 'You already have an active booking for this tour date.',
                ]);
            }

            /*
         * Calculate currently occupied capacity.
         *
         * Cancelled bookings are intentionally excluded.
         */
            $bookedGuests = $tourDate
                ->bookings()
                ->whereIn('booking_status', [
                    'pending',
                    'confirmed',
                ])
                ->sum('number_of_guests');

            /*
         * Calculate actual remaining capacity.
         */
            $remainingCapacity = max(
                0,
                $tourDate->capacity - $bookedGuests
            );

            /*
         * Make sure the requested number of guests
         * fits inside the remaining capacity.
         */
            if (
                $request->number_of_guests >
                $remainingCapacity
            ) {
                throw ValidationException::withMessages([
                    'number_of_guests' => 'There are only '.
                        $remainingCapacity.
                        ' guest(s) remaining.',
                ]);
            }

            /*
         * Verify pickup schedule belongs
         * to this tour date.
         */
            $pickupScheduleExists = $tourDate
                ->pickupSchedules()
                ->where(
                    'id',
                    $request->pickup_schedule_id
                )
                ->exists();

            abort_unless(
                $pickupScheduleExists,
                422
            );

            /*
         * Create the booking.
         */
            return Booking::create([
                'user_id' => auth()->id(),

                'tour_date_id' => $tourDate->id,

                'pickup_schedule_id' => $request->pickup_schedule_id,

                'pickup_latitude' => $request->pickup_latitude,

                'pickup_longitude' => $request->pickup_longitude,

                'number_of_guests' => $request->number_of_guests,

                'phone_number' => $request->phone_number,

                'nationality' => $request->nationality,

                'special_request' => $request->special_request,

                'booking_status' => 'pending',
            ]);
        });

        // Notify Operators
        $operators = User::whereHas('role', fn ($q) => $q->whereIn('name', ['Admin', 'Superadmin']))->get();
        if ($operators->isNotEmpty()) {
            Notification::send($operators, new BookingCreatedNotification($booking));
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Booking created. Please complete your payment.',
        ]);

        /*
     * Always continue to payment after
     * successful booking.
     */
        return redirect()
            ->route(
                'user.bookings.payment',
                $booking
            );
    }

    /**
     * Edit booking.
     */
    public function edit(
        Booking $booking
    ): Response {
        $this->authorizeBooking($booking);

        abort_unless(
            $booking->booking_status === 'pending',
            403
        );

        $booking->load([
            'tourDate.package',
            'tourDate.times',
            'tourDate.pickupSchedules.pickupLocation',
            'pickupSchedule.pickupLocation',
            'payments',
        ]);

        $tourDate = $booking->tourDate;

        $bookedGuests = $tourDate
            ->bookings()
            ->whereIn('booking_status', [
                'pending',
                'confirmed',
            ])
            ->where(
                'id',
                '!=',
                $booking->id
            )
            ->sum('number_of_guests');

        $remainingCapacity = max(
            0,
            $tourDate->capacity - $bookedGuests
        );

        return Inertia::render(
            'user/bookings/Edit',
            [
                'booking' => $booking,

                'tourDate' => [
                    'id' => $tourDate->id,

                    'tour_date' => $tourDate->tour_date->toDateString(),

                    'capacity' => $tourDate->capacity,

                    'booked_guests' => $bookedGuests,

                    'remaining_capacity' => $remainingCapacity,

                    'package' => $this->packageRoutePayload($tourDate->package),

                    'times' => $tourDate->times,

                    'pickup_schedules' => $tourDate->pickupSchedules,
                ],
            ]
        );
    }

    /**
     * Update booking.
     *
     * ONLY used for editing.
     */
    public function update(
        Request $request,
        Booking $booking
    ): RedirectResponse {
        $this->authorizeBooking($booking);

        abort_unless(
            $booking->booking_status === 'pending',
            403
        );

        $validated = $request->validate([
            'pickup_schedule_id' => [
                'required',
                'integer',
            ],

            'pickup_latitude' => [
                'required',
                'numeric',
                'between:4,22',
            ],

            'pickup_longitude' => [
                'required',
                'numeric',
                'between:116,128',
            ],

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
        ]);

        $tourDate = $booking->tourDate;

        /*
         * Verify pickup schedule belongs
         * to the booking's tour date.
         */
        $pickupExists = $tourDate
            ->pickupSchedules()
            ->where(
                'id',
                $validated['pickup_schedule_id']
            )
            ->exists();

        abort_unless(
            $pickupExists,
            422
        );

        /*
         * Calculate capacity excluding
         * current booking.
         */
        $bookedGuests = $tourDate
            ->bookings()
            ->whereIn('booking_status', [
                'pending',
                'confirmed',
            ])
            ->where(
                'id',
                '!=',
                $booking->id
            )
            ->sum('number_of_guests');

        $remainingCapacity = max(
            0,
            $tourDate->capacity - $bookedGuests
        );

        if (
            $validated['number_of_guests'] >
            $remainingCapacity
        ) {
            return back()
                ->withErrors([
                    'number_of_guests' => 'There are only '.
                        $remainingCapacity.
                        ' guest(s) remaining.',
                ])
                ->withInput();
        }

        $booking->update([
            'pickup_schedule_id' => $validated['pickup_schedule_id'],

            'pickup_latitude' => $validated['pickup_latitude'],

            'pickup_longitude' => $validated['pickup_longitude'],

            'number_of_guests' => $validated['number_of_guests'],

            'phone_number' => $validated['phone_number'],

            'nationality' => $validated['nationality'],

            'special_request' => $validated['special_request'] ?? null,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Booking updated successfully.',
        ]);

        return redirect()
            ->route(
                'user.bookings.show',
                $booking
            );
    }

    /**
     * Cancel / archive booking.
     *
     * IMPORTANT:
     * This is separate from update().
     */
    public function cancel(
        Booking $booking
    ): RedirectResponse {
        $this->authorizeBooking($booking);

        if ($booking->booking_status !== 'pending') {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'Only pending bookings can be cancelled. Confirmed bookings cannot be cancelled.',
            ]);

            return redirect()
                ->route(
                    'user.bookings.index'
                );
        }

        $booking->update([
            'booking_status' => 'cancelled',
        ]);

        if ($booking->payments()->exists()) {
            $booking->payments()->update([
                'payment_status' => 'cancelled',
                'paid_at' => null,
            ]);
        }

        // Notify Operators of cancellation
        $operators = User::whereHas('role', fn ($q) => $q->whereIn('name', ['Admin', 'Superadmin']))->get();
        if ($operators->isNotEmpty()) {
            Notification::send($operators, new BookingCancelledNotification($booking, 'Tourist'));
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Booking #{$booking->id} has been cancelled and moved to your History.",
        ]);

        return redirect()
            ->route(
                'user.history'
            );
    }

    /**
     * Archive booking from tourist history.
     */
    public function archive(
        Booking $booking
    ): RedirectResponse {
        $this->authorizeBooking($booking);

        if (! in_array($booking->booking_status, ['completed', 'cancelled'], true)) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'Only completed or cancelled bookings can be archived from history.',
            ]);

            return redirect()->route('user.history');
        }

        $booking->update([
            'is_archived' => true,
            'archived_at' => now(),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Booking #{$booking->id} has been archived and removed from your history list.",
        ]);

        return redirect()->route('user.history');
    }

    /**
     * Clear / archive all completed and cancelled bookings from tourist history.
     */
    public function clearHistory(): RedirectResponse
    {
        $updated = Booking::query()
            ->where('user_id', auth()->id())
            ->whereIn('booking_status', ['completed', 'cancelled'])
            ->where('is_archived', false)
            ->update([
                'is_archived' => true,
                'archived_at' => now(),
            ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $updated > 0
                ? "Successfully cleared {$updated} past booking(s) from your history."
                : 'No past booking records to clear.',
        ]);

        return redirect()->route('user.history');
    }

    /**
     * Permanently delete cancelled booking.
     */
    public function destroy(
        Booking $booking
    ): RedirectResponse {
        $this->authorizeBooking($booking);

        if (
            $booking->booking_status !==
            'cancelled'
        ) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'Only cancelled bookings can be permanently deleted.',
            ]);

            return redirect()
                ->route(
                    'user.bookings.index'
                );
        }

        DB::transaction(function () use ($booking) {
            $this->files->deleteDirectory(
                UploadPath::payment($booking->id)
            );

            $booking->payments()->delete();

            $booking->delete();
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Booking permanently deleted.',
        ]);

        return redirect()
            ->route(
                'user.bookings.index'
            );
    }

    /**
     * Show payment page.
     */
    public function payment(
        Booking $booking
    ): Response|RedirectResponse {
        $this->authorizeBooking($booking);

        if (in_array($booking->booking_status, ['confirmed', 'completed', 'cancelled'])) {
            return redirect()->route('user.bookings.show', $booking);
        }

        $booking->load([
            'tourDate.package',
            'pickupSchedule.pickupLocation',
            'payments',
        ]);

        $existingPayment = $booking->payments->first();
        $existingPaymentPayload = $existingPayment?->toArray();

        return Inertia::render(
            'user/bookings/Payment',
            [
                'booking' => $booking,
                'existingPayment' => $existingPaymentPayload,
                'paymongoEnabled' => $this->paymongo->isConfigured(),
            ]
        );
    }

    /**
     * Submit or update payment.
     */
    public function submitPayment(
        Request $request,
        Booking $booking
    ): RedirectResponse {
        $this->authorizeBooking($booking);

        abort_unless(
            $booking->booking_status === 'pending',
            403
        );

        $existingPayment = Payment::where('booking_id', $booking->id)->first();
        if ($existingPayment && in_array($existingPayment->payment_status, ['paid', 'confirmed'])) {
            return redirect()->route('user.bookings.show', $booking);
        }

        return back()->withErrors([
            'payment_method' => 'Cash on-site is recorded by the boat operator. Pay online with GCash.',
        ]);
    }

    /**
     * Show booking details.
     */
    public function show(
        Booking $booking
    ): Response {

        abort_unless(
            $booking->user_id === auth()->id(),
            403
        );

        $booking->load([
            'tourDate.package',
            'tourDate.times',
            'tourDate.weatherForecast',
            'pickupSchedule.pickupLocation',
            'payments',
        ]);

        $package = $booking->tourDate?->package;
        $packagePayload = null;

        if ($package) {
            $route = $package->routeSummary();
            $packagePayload = [
                ...$package->toArray(),
                'stops' => $route['stops'],
                'island_count' => $route['island_count'],
                'image' => null,
                'image_urls' => array_column(
                    $this->files->images(
                        UploadPath::package($package->id)
                    ),
                    'url'
                ),
            ];
        }

        $paymentsPayload = $booking->payments
            ->map(fn ($payment) => $payment->toArray())
            ->values();

        $bookingPayload = [
            ...$booking->toArray(),
            'package' => $packagePayload,
            'tour_date' => $booking->tourDate ? [
                ...$booking->tourDate->toArray(),
                'package' => $packagePayload,
                'times' => $booking->tourDate->times,
                'weather_forecast' => $booking->tourDate->weatherForecast,
            ] : null,
            'pickup_schedule' => $booking->pickupSchedule ? [
                ...$booking->pickupSchedule->toArray(),
                'pickup_location' => $booking->pickupSchedule->pickupLocation,
            ] : null,
            'payments' => $paymentsPayload,
        ];

        /*
     * ================================================================
     * CAPACITY
     * ================================================================
     *
     * Only pending and confirmed bookings consume capacity.
     *
     * IMPORTANT:
     * The current booking is intentionally INCLUDED here.
     *
     * Example:
     *
     * Tour capacity = 20
     * Current active bookings = 19 guests
     *
     * Remaining = 20 - 19 = 1
     *
     * If this booking itself contains the last guest(s), the page
     * correctly reflects the actual remaining capacity after this
     * booking is counted.
     */
        $bookedGuests = $booking
            ->tourDate
            ->bookings()
            ->whereIn('booking_status', [
                'pending',
                'confirmed',
            ])
            ->sum('number_of_guests');

        $remainingCapacity = max(
            0,
            $booking->tourDate->capacity - $bookedGuests
        );

        return Inertia::render(
            'user/bookings/Show',
            [
                'booking' => $bookingPayload,

                /*
             * Send capacity information to Vue.
             */
                'capacity' => [
                    'total' => $booking->tourDate->capacity,

                    'booked_guests' => $bookedGuests,

                    'remaining_guests' => $remainingCapacity,
                ],

                'caseRequest' => BookingCaseRequest::query()
                    ->where('booking_id', $booking->id)
                    ->latest('id')
                    ->first([
                        'id',
                        'reason',
                        'requested_resolution',
                        'details',
                        'status',
                        'admin_note',
                    ]),

                'refundRequest' => ($refundRequest = RefundRequest::query()
                    ->where('booking_id', $booking->id)
                    ->latest('id')
                    ->first())
                    ? [
                        'id' => $refundRequest->id,
                        'payment_id' => $refundRequest->payment_id,
                        'category' => $refundRequest->category,
                        'category_label' => $refundRequest->categoryLabel(),
                        'reason' => $refundRequest->reason,
                        'status' => $refundRequest->status,
                        'status_label' => $refundRequest->statusLabel(),
                        'rejection_reason' => $refundRequest->rejection_reason,
                        'created_at' => $refundRequest->created_at?->toDateTimeString(),
                        'reviewed_at' => $refundRequest->reviewed_at?->toDateTimeString(),
                        'processed_at' => $refundRequest->processed_at?->toDateTimeString(),
                    ]
                    : null,
            ]
        );
    }

    /**
     * QR code page/data.
     *
     * This keeps the QR token private to
     * the authenticated booking owner.
     */
    public function qr(
        Booking $booking
    ): Response {
        $this->authorizeBooking($booking);

        abort_unless(
            in_array($booking->booking_status, ['confirmed', 'completed'], true)
            && ! empty($booking->qr_token),
            403
        );

        $booking->load([
            'tourDate.package',
            'tourDate.weatherForecast',
            'pickupSchedule.pickupLocation',
        ]);

        return Inertia::render(
            'user/bookings/Qr',
            [
                'booking' => $booking,
            ]
        );
    }

    /**
     * Make sure the booking belongs
     * to the authenticated user.
     */
    private function authorizeBooking(Booking $booking): void
    {
        abort_unless(
            $booking->user_id === auth()->id(),
            403
        );
    }

    /**
     * @return array{id: int, package_name: string, destination: string|null, price: mixed, stops: list<string>, island_count: int}
     */
    private function packageRoutePayload(Package $package): array
    {
        $route = $package->routeSummary();

        return [
            'id' => $package->id,
            'package_name' => $package->package_name,
            'destination' => $package->destination,
            'price' => $package->price,
            'stops' => $route['stops'],
            'island_count' => $route['island_count'],
        ];
    }
}
