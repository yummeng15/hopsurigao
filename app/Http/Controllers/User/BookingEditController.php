<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Safe Tourist edits of a pending booking's non-financial information.
 * The original tour date, guests, and price snapshot never change here.
 */
class BookingEditController extends Controller
{
    public function edit(Request $request, Booking $booking): Response
    {
        $this->authorizeEditable($booking, (int) $request->user()->id);

        $booking->load([
            'tourDate.package',
            'tourDate.pickupSchedules.pickupLocation',
            'payments',
        ]);

        $tourDate = $booking->tourDate;

        return Inertia::render('user/bookings/Edit', [
            'booking' => [
                'id' => (int) $booking->id,
                'pickup_schedule_id' => (int) $booking->pickup_schedule_id,
                'pickup_latitude' => $booking->pickup_latitude,
                'pickup_longitude' => $booking->pickup_longitude,
                'number_of_guests' => (int) $booking->number_of_guests,
                'phone_number' => (string) $booking->phone_number,
                'nationality' => (string) $booking->nationality,
                'special_request' => $booking->special_request,
                'booked_unit_price' => $booking->booked_unit_price,
                'booked_total' => $booking->booked_total,
                'price_snapshot_source' => $booking->price_snapshot_source,
                'has_pending_checkout' => $booking->payments->contains(
                    fn (Payment $payment): bool =>
                        $payment->gateway === 'paymongo'
                        && $payment->payment_status === 'pending'
                ),
            ],
            'tourDate' => [
                'id' => (int) $tourDate->id,
                'tour_date' => $tourDate->tour_date->toDateString(),
                'package' => [
                    'id' => (int) $tourDate->package->id,
                    'package_name' => (string) $tourDate->package->package_name,
                    'destination' => (string) $tourDate->package->destination,
                ],
                'pickup_schedules' => $tourDate->pickupSchedules->map(
                    fn ($schedule): array => [
                        'id' => (int) $schedule->id,
                        'pickup_time' => $schedule->pickup_time?->format('H:i:s'),
                        'pickup_location' => $schedule->pickupLocation ? [
                            'name' => (string) $schedule->pickupLocation->name,
                            'latitude' => $schedule->pickupLocation->latitude,
                            'longitude' => $schedule->pickupLocation->longitude,
                        ] : null,
                    ]
                )->values()->all(),
            ],
        ]);
    }

    public function update(Request $request, Booking $booking): RedirectResponse
    {
        $this->authorizeEditable($booking, (int) $request->user()->id);

        // Prohibited fields cannot be changed by manually modifying the
        // browser request. The Vue form does not submit them at all.
        $data = $request->validate([
            'tour_date_id' => ['prohibited'],
            'package_id' => ['prohibited'],
            'number_of_guests' => ['prohibited'],
            'booked_unit_price' => ['prohibited'],
            'booked_total' => ['prohibited'],
            'price_snapshot_source' => ['prohibited'],
            'booking_status' => ['prohibited'],
            'payment_status' => ['prohibited'],
            'payment_method' => ['prohibited'],
            'amount' => ['prohibited'],
            'pickup_schedule_id' => ['required', 'integer'],
            'pickup_latitude' => ['required', 'numeric', 'between:4,22'],
            'pickup_longitude' => ['required', 'numeric', 'between:116,128'],
            'phone_number' => ['required', 'string', 'max:255'],
            'nationality' => ['required', 'string', 'max:255'],
            'special_request' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($booking, $request, $data): void {
            // Lock before verifying status; a webhook might be confirming
            // this booking while the Tourist submits an edit.
            $locked = Booking::query()
                ->whereKey($booking->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->authorizeEditable($locked, (int) $request->user()->id);

            $sameDateSchedule = $locked->tourDate
                ->pickupSchedules()
                ->whereKey($data['pickup_schedule_id'])
                ->exists();

            if (! $sameDateSchedule) {
                throw ValidationException::withMessages([
                    'pickup_schedule_id' => 'Select a pickup schedule for your original tour date.',
                ]);
            }

            $locked->update([
                'pickup_schedule_id' => $data['pickup_schedule_id'],
                'pickup_latitude' => $data['pickup_latitude'],
                'pickup_longitude' => $data['pickup_longitude'],
                'phone_number' => $data['phone_number'],
                'nationality' => $data['nationality'],
                'special_request' => $data['special_request'] ?? null,
            ]);
        }, 3);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Booking details updated. Your tour date, guests, and agreed price are unchanged.',
        ]);

        return redirect()->route('user.bookings.show', $booking->id);
    }

    private function authorizeEditable(Booking $booking, int $touristId): void
    {
        abort_unless((int) $booking->user_id === $touristId, 403);
        abort_unless($booking->booking_status === 'pending', 403);

        // A stale pending booking must not remain editable if a payment
        // has already been confirmed by the gateway.
        abort_if(
            $booking->payments()
                ->whereIn('payment_status', ['paid', 'partial'])
                ->exists(),
            403,
            'A booking with a recorded payment can no longer be edited.'
        );
    }
}
