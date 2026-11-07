<?php

namespace App\Notifications;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PaymentSubmittedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Payment $payment
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $this->payment->loadMissing(['booking.user', 'booking.tourDate.package']);

        $customerName = $this->payment->booking?->user?->name ?? 'Guest';
        $formattedAmount = '₱'.number_format((float) $this->payment->amount, 2);
        $method = ucfirst(str_replace('_', ' ', (string) $this->payment->payment_method));

        $role = $notifiable instanceof User
            ? $notifiable->role?->name
            : null;

        $bookingId = $this->payment->booking_id;

        $actionUrl = $role === 'Admin'
            ? "/operator/payments/{$bookingId}"
            : "/admin/payments?search={$bookingId}";

        return [
            'type' => 'payment_submitted',
            'title' => 'Payment Proof Submitted',
            'message' => "{$customerName} submitted a {$method} payment of {$formattedAmount} for booking #{$bookingId} waiting for verification.",
            'action_url' => $actionUrl,
            'action_text' => 'Verify Payment',
            'related_entity' => 'payment',
            'related_id' => $this->payment->id,
            'priority' => 'normal',
            'icon' => 'credit-card',
            'metadata' => [
                'payment_id' => $this->payment->id,
                'booking_id' => $this->payment->booking_id,
                'amount' => (float) $this->payment->amount,
                'payment_method' => $this->payment->payment_method,
                'transaction_reference' => $this->payment->transaction_reference,
                'customer_name' => $customerName,
            ],
        ];
    }
}
