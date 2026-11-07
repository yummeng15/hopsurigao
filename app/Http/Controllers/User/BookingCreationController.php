<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreBookingRequest;
use App\Models\Booking;
use App\Models\Package;
use App\Models\PickupSchedule;
use App\Models\TourDate;
use App\Models\TourOperator;
use App\Models\User;
use App\Notifications\BookingCreatedNotification;
use App\Services\TourDateBookingAvailability;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/** The only endpoint that creates a Tourist reservation. */
class BookingCreationController extends Controller
{
    public function __construct(
        private TourDateBookingAvailability $availability,
    ) {}

    public function __invoke(StoreBookingRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $touristId = (int) $request->user()->id;

        $booking = DB::transaction(function () use ($data, $touristId): Booking {
            // All Tourist and Operator reservations lock the tour date
            // before checking remaining seats.
            $date = TourDate::query()
                ->whereKey($data['tour_date_id'])
                ->lockForUpdate()
                ->firstOrFail();

            // Lock the package as well so its current availability and
            // price cannot change while the booking snapshot is created.
            $package = Package::query()
                ->whereKey($date->package_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($package->status !== 'active') {
                throw ValidationException::withMessages([
                    'tour_date_id' => 'This tour package is unavailable.',
                ]);
            }

            if ($package->tour_operator_id !== null) {
                TourOperator::query()
                    ->whereKey($package->tour_operator_id)
                    ->lockForUpdate()
                    ->first();
            }

            if ($date->tour_date->toDateString() < today()->toDateString()) {
                throw ValidationException::withMessages([
                    'tour_date_id' => 'Please select today or a future tour date.',
                ]);
            }

            $forecast = $date->weatherForecast()->lockForUpdate()->first();
            $reservedPreview = (int) $date->bookings()
                ->whereIn('booking_status', ['pending', 'confirmed'])
                ->sum('number_of_guests');

            $assessment = $this->availability->assess(
                $package,
                $date,
                $reservedPreview,
                $forecast?->advisory_level,
                $forecast !== null,
            );

            if (! $assessment['bookable']) {
                throw ValidationException::withMessages([
                    'tour_date_id' => $assessment['reason'] ?? 'This tour date is unavailable.',
                ]);
            }

            $pickupValid = PickupSchedule::query()
                ->whereKey($data['pickup_schedule_id'])
                ->where('tour_date_id', $date->id)
                ->whereHas('pickupLocation', fn ($q) => $q
                    ->where('status', 'active')
                    ->where(fn ($locations) => $locations
                        ->whereNull('tour_operator_id')
                        ->orWhere('tour_operator_id', $package->tour_operator_id)))
                ->exists();

            if (! $pickupValid) {
                throw ValidationException::withMessages([
                    'pickup_schedule_id' => 'Select an active pickup location for this tour operator and date.',
                ]);
            }

            $duplicate = Booking::query()
                ->where('user_id', $touristId)
                ->where('tour_date_id', $date->id)
                ->whereIn('booking_status', ['pending', 'confirmed'])
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages([
                    'tour_date_id' => 'You already have an active reservation for this tour date.',
                ]);
            }

            $reserved = (int) $date->bookings()
                ->whereIn('booking_status', ['pending', 'confirmed'])
                ->sum('number_of_guests');

            $remaining = max(0, (int) $date->capacity - $reserved);

            if ((int) $data['number_of_guests'] > $remaining) {
                throw ValidationException::withMessages([
                    'number_of_guests' => "Only {$remaining} guest(s) remain for this date.",
                ]);
            }

            if (! is_numeric($package->price) || (float) $package->price <= 0) {
                throw ValidationException::withMessages([
                    'tour_date_id' => 'The package price is invalid. Contact the operator.',
                ]);
            }

            // Booking::creating (Fix 4A) freezes booked_unit_price
            // and booked_total. Do not accept a price from the browser.
            return Booking::query()->create([
                'user_id' => $touristId,
                'tour_date_id' => $date->id,
                'pickup_schedule_id' => (int) $data['pickup_schedule_id'],
                'pickup_latitude' => $data['pickup_latitude'],
                'pickup_longitude' => $data['pickup_longitude'],
                'number_of_guests' => (int) $data['number_of_guests'],
                'phone_number' => $data['phone_number'],
                'nationality' => $data['nationality'],
                'special_request' => $data['special_request'] ?? null,
                'booking_status' => 'pending',
            ]);
        }, 3);

        // Notifications occur only AFTER commit. Failure never creates
        // an extra reservation or erases a successful booking.
        try {
            $booking->loadMissing('tourDate.package');
            $operatorId = $booking->tourDate?->package?->tour_operator_id;

            $recipients = User::query()
                ->where(function ($query) use ($operatorId): void {
                    $query->whereHas('role', fn ($roles) => $roles->where('name', 'Superadmin'));

                    if ($operatorId !== null) {
                        $query->orWhere(function ($admins) use ($operatorId): void {
                            $admins->where('tour_operator_id', $operatorId)
                                ->whereHas('role', fn ($roles) => $roles->where('name', 'Admin'));
                        });
                    }
                })
                ->get();

            if ($recipients->isNotEmpty()) {
                Notification::send($recipients, new BookingCreatedNotification($booking));
            }
        } catch (\Throwable $exception) {
            report($exception);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Booking created. Please complete your payment.',
        ]);

        return redirect()->route('user.bookings.payment', $booking);
    }
}
