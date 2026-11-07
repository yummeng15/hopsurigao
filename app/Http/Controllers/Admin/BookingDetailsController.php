<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Payment;
use App\Services\FileUploadService;
use App\Services\UploadPath;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Read-only platform oversight of one booking.
 * No financial or booking mutation is exposed here.
 */
class BookingDetailsController extends Controller
{
    public function __construct(private FileUploadService $files) {}

    public function __invoke(Request $request, Booking $booking): Response
    {
        abort_unless($request->user()?->role?->name === 'Superadmin', 403);

        $booking->load([
            'user:id,name,email',
            'tourDate.package',
            'tourDate.times',
            'pickupSchedule.pickupLocation',
            'payments',
        ]);

        $package = $booking->tourDate?->package;

        $images = $package !== null
            ? array_column($this->files->images(UploadPath::package($package->id)), 'url')
            : [];

        // These fields were added by Fix 4A. A null/legacy price must never
        // be presented as the verified original agreement.
        $savedTotal = $booking->booked_total !== null
            ? (float) $booking->booked_total
            : null;

        $savedUnit = $booking->booked_unit_price !== null
            ? (float) $booking->booked_unit_price
            : null;

        $received = $booking->amountReceived();
        $remaining = $savedTotal !== null
            ? round(max(0, $savedTotal - $received), 2)
            : null;

        $needsReview = $savedTotal === null
            || $savedTotal <= 0
            || $booking->price_snapshot_source !== 'at_booking';

        $hasPending = $booking->payments->contains(
            fn (Payment $payment): bool => $payment->payment_status === 'pending'
        );

        $overallStatus = $needsReview
            ? 'review'
            : ($received >= $savedTotal - 0.005
                ? 'paid'
                : ($received > 0 ? 'partial' : ($hasPending ? 'pending' : 'unpaid')));

        return Inertia::render('admin/bookings/Show', [
            'booking' => [
                'id' => $booking->id,
                'booking_status' => $booking->booking_status,
                'number_of_guests' => (int) $booking->number_of_guests,
                'phone_number' => $booking->phone_number,
                'nationality' => $booking->nationality,
                'special_request' => $booking->special_request,
                'qr_token' => $booking->qr_token,
                'pickup_latitude' => $booking->pickup_latitude,
                'pickup_longitude' => $booking->pickup_longitude,
                'created_at' => $booking->created_at?->toIso8601String(),
                'updated_at' => $booking->updated_at?->toIso8601String(),
                'user' => $booking->user ? [
                    'id' => $booking->user->id,
                    'name' => $booking->user->name,
                    'email' => $booking->user->email,
                ] : null,
                'package' => $package ? [
                    'id' => $package->id,
                    'package_name' => $package->package_name,
                    'destination' => $package->destination,
                    'description' => $package->description,
                    'status' => $package->status,
                    'image_urls' => $images,
                ] : null,
                'tour_date' => $booking->tourDate ? [
                    'id' => $booking->tourDate->id,
                    'tour_date' => $booking->tourDate->tour_date?->toDateString(),
                    'capacity' => (int) $booking->tourDate->capacity,
                    'times' => $booking->tourDate->times->map(fn ($time): array => [
                        'id' => $time->id,
                        'time' => (string) $time->time,
                        'description' => $time->description,
                    ])->values()->all(),
                ] : null,
                'pickup_schedule' => $booking->pickupSchedule ? [
                    'id' => $booking->pickupSchedule->id,
                    'pickup_time' => $booking->pickupSchedule->pickup_time?->format('H:i:s'),
                    'pickup_location' => $booking->pickupSchedule->pickupLocation ? [
                        'id' => $booking->pickupSchedule->pickupLocation->id,
                        'name' => $booking->pickupSchedule->pickupLocation->name,
                        'address' => $booking->pickupSchedule->pickupLocation->address,
                    ] : null,
                ] : null,
                'booked_unit_price' => $savedUnit,
                'booked_total' => $savedTotal,
                'price_snapshot_source' => $booking->price_snapshot_source,
                'amount_received' => round($received, 2),
                'remaining_balance' => $remaining,
                'price_needs_review' => $needsReview,
                'overall_payment_status' => $overallStatus,
                'payments' => $booking->payments
                    ->sortByDesc('id')
                    ->map(fn (Payment $payment): array => [
                        'id' => $payment->id,
                        'amount' => (float) $payment->amount,
                        'payment_method' => $payment->payment_method,
                        'payment_status' => $payment->payment_status,
                        'gateway' => $payment->gateway,
                        'transaction_reference' => $payment->transaction_reference,
                        'notes' => $payment->notes,
                        'paid_at' => $payment->paid_at?->toIso8601String(),
                        'created_at' => $payment->created_at?->toIso8601String(),
                    ])
                    ->values()
                    ->all(),
            ],
        ]);
    }
}
