<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\RefundRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RefundRequestController extends Controller
{
    public function store(Request $request, Booking $booking): RedirectResponse
    {
        abort_unless($booking->user_id === $request->user()?->id, 403);

        abort_unless(
            in_array($booking->booking_status, ['confirmed', 'completed'], true),
            403,
            'Only confirmed or completed bookings can request a refund.'
        );

        $validated = $request->validate([
            'category' => ['required', Rule::in(RefundRequest::ELIGIBLE_CATEGORIES)],
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        DB::transaction(function () use ($booking, $request, $validated): void {
            $payment = Payment::query()
                ->where('booking_id', $booking->id)
                ->where('payment_status', 'paid')
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if ($payment === null) {
                throw ValidationException::withMessages([
                    'reason' => 'Only a paid booking can request a refund under the cancellation policy.',
                ]);
            }

            $openExists = RefundRequest::query()
                ->where('payment_id', $payment->id)
                ->whereIn('status', [
                    RefundRequest::STATUS_PENDING,
                    RefundRequest::STATUS_APPROVED,
                    RefundRequest::STATUS_REFUNDED_TEST,
                ])
                ->exists();

            if ($openExists) {
                throw ValidationException::withMessages([
                    'reason' => 'A refund request already exists for this payment.',
                ]);
            }

            $booking->loadMissing('tourDate.package');

            $operatorId = $booking->tourDate?->package?->tour_operator_id;

            abort_unless($operatorId !== null, 422, 'This booking has no assigned tour operator.');

            RefundRequest::query()->create([
                'booking_id' => $booking->id,
                'payment_id' => $payment->id,
                'user_id' => $request->user()->id,
                'tour_operator_id' => $operatorId,
                'category' => $validated['category'],
                'reason' => $validated['reason'],
                'status' => RefundRequest::STATUS_PENDING,
            ]);
        });

        return back()->with(
            'success',
            'Refund request submitted. Your tour operator will review it. No automatic refund is issued.'
        );
    }
}
