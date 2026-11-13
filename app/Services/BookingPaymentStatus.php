<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Payment;

/**
 * Resolve booking-level payment labels from verified Payment rows.
 * Booking status alone never implies paid.
 */
class BookingPaymentStatus
{
    /**
     * @return array{
     *     status: string,
     *     label: string,
     *     total_amount: float,
     *     amount_paid: float,
     *     remaining_balance: float,
     *     payment_method: string|null,
     *     payment_gateway: string|null,
     *     transaction_reference: string|null,
     *     paid_at: string|null,
     *     payments: list<array{
     *         id: int,
     *         amount: float,
     *         payment_method: string|null,
     *         payment_status: string,
     *         gateway: string|null,
     *         transaction_reference: string|null,
     *         paid_at: string|null,
     *         channel_label: string
     *     }>
     * }
     */
    public function summarize(Booking $booking): array
    {
        $booking->loadMissing('payments');

        $total = round($booking->packageTotal(), 2);
        $amountPaid = round($booking->amountReceived(), 2);
        $payments = $booking->payments->sortByDesc('id')->values();

        $hasSuccessful = $payments->contains(
            fn (Payment $payment): bool => in_array($payment->payment_status, ['paid', 'partial'], true)
        );
        $hasPending = $payments->contains(
            fn (Payment $payment): bool => $payment->payment_status === 'pending'
        );
        $hasFailed = $payments->contains(
            fn (Payment $payment): bool => in_array($payment->payment_status, ['failed', 'cancelled'], true)
        );
        $allRefunded = $payments->isNotEmpty()
            && $payments->every(fn (Payment $payment): bool => $payment->payment_status === 'refunded');

        if ($total > 0 && $amountPaid >= $total - 0.005) {
            $status = 'paid';
            $label = 'PAID';
        } elseif ($amountPaid > 0) {
            $status = 'partial';
            $label = 'PARTIAL';
        } elseif ($allRefunded) {
            $status = 'refunded';
            $label = 'REFUNDED';
        } elseif ($hasPending && ! $hasSuccessful) {
            $status = 'pending';
            $label = 'PENDING';
        } elseif ($hasFailed && ! $hasSuccessful && ! $hasPending) {
            $status = 'failed';
            $label = 'FAILED';
        } else {
            $status = 'unpaid';
            $label = 'UNPAID';
        }

        $displayPayment = $payments->first(
            fn (Payment $payment): bool => in_array($payment->payment_status, ['paid', 'partial'], true)
                && $payment->paid_at !== null
        ) ?? $payments->first();

        return [
            'status' => $status,
            'label' => $label,
            'total_amount' => $total,
            'amount_paid' => $amountPaid,
            'remaining_balance' => round(max(0, $total - $amountPaid), 2),
            'payment_method' => $displayPayment?->payment_method,
            'payment_gateway' => $displayPayment?->gateway,
            'transaction_reference' => $displayPayment?->transaction_reference,
            'paid_at' => $displayPayment?->paid_at?->format('Y-m-d H:i'),
            'payments' => $payments->map(function (Payment $payment): array {
                return [
                    'id' => (int) $payment->id,
                    'amount' => round((float) $payment->amount, 2),
                    'payment_method' => $payment->payment_method,
                    'payment_status' => (string) $payment->payment_status,
                    'gateway' => $payment->gateway,
                    'transaction_reference' => $payment->transaction_reference,
                    'paid_at' => $payment->paid_at?->format('Y-m-d H:i'),
                    'channel_label' => $this->channelLabel($payment),
                ];
            })->all(),
        ];
    }

    public function channelLabel(Payment $payment): string
    {
        $gateway = strtolower((string) $payment->gateway);
        $method = strtolower((string) $payment->payment_method);

        if ($gateway === 'paymongo' || $method === 'gcash') {
            return 'GCash (PayMongo Test Mode)';
        }

        if (in_array($method, ['cash', 'Cash'], true) || $method === 'cash') {
            return 'Walk-in Cash';
        }

        if ($method === '') {
            return 'Unspecified';
        }

        return (string) $payment->payment_method;
    }
}
