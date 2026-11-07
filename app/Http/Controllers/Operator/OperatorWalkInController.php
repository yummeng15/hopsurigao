<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreOnsiteBookingRequest;
use App\Models\Booking;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Role;
use App\Models\TourDate;
use App\Models\User;
use App\Notifications\BookingConfirmedNotification;
use App\Notifications\BookingCreatedNotification;
use App\Notifications\PaymentConfirmedNotification;
use App\Services\WeatherAdvisoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Operator-owned, cash-only walk-in registration. */
class OperatorWalkInController extends Controller
{
    public function __construct(
        private WeatherAdvisoryService $weather
    ) {}

    public function create(Request $request): Response
    {
        $operatorId = $this->operatorId($request);

        $packages = Package::query()
            ->where('tour_operator_id', $operatorId)
            ->where('status', 'active')
            ->where('under_maintenance', false)
            ->whereHas('tourDates', fn ($dates) => $dates
                ->whereDate('tour_date', '>=', today()->toDateString()))
            ->orderBy('package_name')
            ->get(['id', 'package_name', 'price', 'destination'])
            ->map(fn ($package) => [
                'id' => (int) $package->id,
                'name' => (string) $package->package_name,
                'destination' => (string) $package->destination,
                'price' => (string) $package->price,
            ])
            ->all();

        return Inertia::render('operator/WalkIn', [
            'packages' => $packages,
        ]);
    }

    public function dates(Request $request, int $package): JsonResponse
    {
        $operatorId = $this->operatorId($request);
        $record = $this->ownedPackage($operatorId, $package);

        abort_unless(
            $record->status === 'active' && ! $record->under_maintenance,
            404
        );

        $dates = $record->tourDates()
            ->whereDate('tour_date', '>=', today()->toDateString())
            ->with([
                'weatherForecast',
                'pickupSchedules.pickupLocation',
            ])
            ->orderBy('tour_date')
            ->limit(120)
            ->get()
            ->map(function (TourDate $date) use ($operatorId): ?array {
                $closure = $this->weather->bookingClosure(
                    (bool) $date->travel_closed,
                    $date->weatherForecast?->advisory_level,
                );

                $reserved = (int) $date->bookings()
                    ->whereIn('booking_status', ['pending', 'confirmed'])
                    ->sum('number_of_guests');

                $remaining = max(
                    0,
                    (int) $date->capacity - $reserved
                );

                if ($closure !== null || $remaining <= 0) {
                    return null;
                }

                $pickups = $date->pickupSchedules
                    ->filter(fn ($pickup) =>
                        $pickup->pickupLocation !== null
                        && $pickup->pickupLocation->status === 'active'
                        && (
                            $pickup->pickupLocation->tour_operator_id === null
                            || (int) $pickup->pickupLocation->tour_operator_id
                                === $operatorId
                        )
                    )
                    ->map(fn ($pickup) => [
                        'id' => (int) $pickup->id,
                        'name' => (string) $pickup->pickupLocation->name,
                        'time' => substr(
                            (string) $pickup->getRawOriginal('pickup_time'),
                            0,
                            5
                        ),
                    ])
                    ->values()
                    ->all();

                if ($pickups === []) {
                    return null;
                }

                return [
                    'id' => (int) $date->id,
                    'date' => $date->tour_date->format('Y-m-d'),
                    'remaining' => $remaining,
                    'pickups' => $pickups,
                ];
            })
            ->filter()
            ->values()
            ->all();

        return response()->json([
            'dates' => $dates,
        ]);
    }

    public function store(StoreOnsiteBookingRequest $request): RedirectResponse
    {
        $operatorId = $this->operatorId($request);

        $request->validate([
            'cash_received' => ['accepted'],
        ]);

        $booking = DB::transaction(
            function () use ($request, $operatorId): Booking {
                // Serialize capacity checks for the selected tour date.
                $date = TourDate::query()
                    ->whereKey($request->integer('tour_date_id'))
                    ->whereHas('package', fn ($packages) => $packages
                        ->where('tour_operator_id', $operatorId))
                    ->lockForUpdate()
                    ->with(['package', 'weatherForecast'])
                    ->firstOrFail();

                $package = $date->package;

                abort_unless(
                    $package !== null
                    && (int) $package->tour_operator_id === $operatorId,
                    404
                );

                if (
                    $package->status !== 'active'
                    || (bool) $package->under_maintenance
                ) {
                    throw ValidationException::withMessages([
                        'tour_date_id' =>
                            'This package is not currently available.',
                    ]);
                }

                if (
                    $date->tour_date->toDateString()
                    < today()->toDateString()
                ) {
                    throw ValidationException::withMessages([
                        'tour_date_id' =>
                            'Choose today or a future tour date.',
                    ]);
                }

                $closure = $this->weather->bookingClosure(
                    (bool) $date->travel_closed,
                    $date->weatherForecast?->advisory_level,
                );

                if ($closure !== null) {
                    throw ValidationException::withMessages([
                        'tour_date_id' => $closure,
                    ]);
                }

                // The pickup must belong to the date and be allowed.
                $pickup = $date->pickupSchedules()
                    ->whereKey($request->integer('pickup_schedule_id'))
                    ->whereHas('pickupLocation', fn ($locations) => $locations
                        ->where('status', 'active')
                        ->where(fn ($allowed) => $allowed
                            ->whereNull('tour_operator_id')
                            ->orWhere('tour_operator_id', $operatorId)))
                    ->first();

                if ($pickup === null) {
                    throw ValidationException::withMessages([
                        'pickup_schedule_id' =>
                            'Select an available pickup point for this tour.',
                    ]);
                }

                $reserved = (int) $date->bookings()
                    ->whereIn('booking_status', ['pending', 'confirmed'])
                    ->sum('number_of_guests');

                $remaining = max(
                    0,
                    (int) $date->capacity - $reserved
                );

                if (
                    $request->integer('number_of_guests') > $remaining
                ) {
                    throw ValidationException::withMessages([
                        'number_of_guests' =>
                            "Only {$remaining} guest(s) remain for this date.",
                    ]);
                }

                $customer = $this->customer($request);

                if (
                    Booking::query()
                        ->where('user_id', $customer->id)
                        ->where('tour_date_id', $date->id)
                        ->whereIn('booking_status', ['pending', 'confirmed'])
                        ->exists()
                ) {
                    throw ValidationException::withMessages([
                        'email' =>
                            'This customer already has an active booking for the chosen date.',
                    ]);
                }

                $total = round(
                    (float) $package->price
                    * $request->integer('number_of_guests'),
                    2
                );

                if ($total <= 0) {
                    throw ValidationException::withMessages([
                        'tour_date_id' => 'Invalid package price.',
                    ]);
                }

                $booking = Booking::query()->create([
                    'user_id' => $customer->id,
                    'tour_date_id' => $date->id,
                    'pickup_schedule_id' => $pickup->id,
                    'number_of_guests' =>
                        $request->integer('number_of_guests'),
                    'phone_number' =>
                        $request->string('phone_number')->toString(),
                    'nationality' =>
                        $request->string('nationality')->toString(),
                    'special_request' =>
                        $request->input('special_request'),
                    'booking_status' => 'confirmed',
                ]);

                Payment::query()->create([
                    'booking_id' => $booking->id,
                    'amount' => $total,
                    'payment_method' => 'Cash',
                    'payment_status' => 'paid',
                    'notes' =>
                        'Cash collected for operator walk-in booking.',
                    'paid_at' => now(),
                ]);

                return $booking;
            }
        );

        // Notification failures must not cause duplicate payments.
        try {
            $recipients = User::query()
                ->where(function ($users) use ($operatorId): void {
                    $users->where(function ($admins) use ($operatorId): void {
                        $admins->where('tour_operator_id', $operatorId)
                            ->whereHas(
                                'role',
                                fn ($roles) =>
                                    $roles->where('name', 'Admin')
                            );
                    })->orWhereHas(
                        'role',
                        fn ($roles) =>
                            $roles->where('name', 'Superadmin')
                    );
                })
                ->get();

            if ($recipients->isNotEmpty()) {
                Notification::send(
                    $recipients,
                    new BookingCreatedNotification($booking)
                );
            }

            $booking->user?->notify(
                new BookingConfirmedNotification($booking)
            );

            $payment = $booking->payments()->first();

            if ($payment) {
                $booking->user?->notify(
                    new PaymentConfirmedNotification($payment)
                );
            }
        } catch (\Throwable $exception) {
            report($exception);
        }

        return redirect()
            ->route('operator.bookings.show', $booking->id)
            ->with(
                'success',
                'Walk-in confirmed and full cash payment recorded.'
            );
    }

    private function operatorId(Request $request): int
    {
        $user = $request->user();

        abort_unless($user?->role?->name === 'Admin', 403);
        abort_unless(
            $user->tourOperator?->is_active === true,
            403
        );

        return (int) $user->tour_operator_id;
    }

    private function ownedPackage(
        int $operatorId,
        int $packageId
    ): Package {
        return Package::query()
            ->where('tour_operator_id', $operatorId)
            ->findOrFail($packageId);
    }

    private function customer(
        StoreOnsiteBookingRequest $request
    ): User {
        $email = mb_strtolower(
            trim((string) $request->input('email'))
        );

        $roleId = Role::query()
            ->where('name', 'User')
            ->firstOrFail()
            ->id;

        if ($email !== '') {
            $existing = User::query()
                ->where('email', $email)
                ->first();

            if ($existing !== null) {
                if ($existing->role?->name !== 'User') {
                    throw ValidationException::withMessages([
                        'email' =>
                            'This email belongs to a staff account.',
                    ]);
                }

                return $existing;
            }
        } else {
            // Generated guest email for walk-in records.
            $phone = preg_replace(
                '/\D+/',
                '',
                $request->string('phone_number')->toString()
            ) ?: 'guest';

            $email = 'onsite.'
                .$phone.'.'
                .Str::lower(Str::random(8))
                .'@hopsurigao.local';
        }

        return User::query()->create([
            'name' =>
                $request->string('customer_name')->toString(),
            'email' => $email,
            'password' => Str::password(),
            'role_id' => $roleId,
        ]);
    }
}
