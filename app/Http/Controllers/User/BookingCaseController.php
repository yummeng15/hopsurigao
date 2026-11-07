<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingCaseRequest;
use App\Models\Payment;
use App\Models\RefundRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BookingCaseController extends Controller
{
    public function store(Request $request, Booking $booking): RedirectResponse
    {
        abort_unless($booking->user_id === $request->user()?->id, 403);

        abort_unless(
            in_array($booking->booking_status, ['confirmed', 'completed'], true),
            403
        );

        $hasVerifiedPayment = $booking->payments()
            ->whereIn('payment_status', ['paid', 'partial'])
            ->exists();

        abort_unless($hasVerifiedPayment, 403);

        $validated = $request->validate([
            'reason' => ['required', Rule::in(RefundRequest::ELIGIBLE_CATEGORIES)],
            'requested_resolution' => ['required', Rule::in(['reschedule', 'refund'])],
            'details' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        /*
         * Full refund review uses Refund Management (Operator).
         * Reschedule stays as an incident case record.
         */
        if ($validated['requested_resolution'] === 'refund') {
            return $this->storeRefundRequest($request, $booking, $validated);
        }

        $open = BookingCaseRequest::query()
            ->where('booking_id', $booking->id)
            ->where('status', 'submitted')
            ->exists();

        if ($open) {
            return back()->withErrors([
                'details' => 'This booking already has a report waiting for review.',
            ]);
        }

        BookingCaseRequest::query()->create([
            'booking_id' => $booking->id,
            'user_id' => $request->user()->id,
            'reason' => $validated['reason'],
            'requested_resolution' => 'reschedule',
            'details' => $validated['details'],
            'status' => 'submitted',
        ]);

        return back()->with(
            'success',
            'Reschedule report submitted. Your tour operator will review it.'
        );
    }

    /**
     * @param  array{reason: string, details: string}  $validated
     */
    private function storeRefundRequest(
        Request $request,
        Booking $booking,
        array $validated
    ): RedirectResponse {
        DB::transaction(function () use ($booking, $request, $validated): void {
            $payment = Payment::query()
                ->where('booking_id', $booking->id)
                ->where('payment_status', 'paid')
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if ($payment === null) {
                throw ValidationException::withMessages([
                    'details' => 'Only a paid booking can request a refund under the cancellation policy.',
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
                    'details' => 'A refund request already exists for this payment.',
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
                'category' => $validated['reason'],
                'reason' => $validated['details'],
                'status' => RefundRequest::STATUS_PENDING,
            ]);
        });

        return back()->with(
            'success',
            'Refund request submitted to your tour operator. No automatic refund is issued.'
        );
    }
}
