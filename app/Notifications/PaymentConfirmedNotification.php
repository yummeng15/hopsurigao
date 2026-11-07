<?php

namespace App\Notifications;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PaymentConfirmedNotification extends Notification
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
        $formattedAmount = '₱' . number_format((float) $this->payment->amount, 2);

        return [
            'type'           => 'payment_confirmed',
            'title'          => 'Payment Confirmed! 💳',
            'message'        => "Your payment of {$formattedAmount} for booking #{$this->payment->booking_id} has been verified and approved.",
            'action_url'     => "/user/bookings/{$this->payment->booking_id}",
            'action_text'    => 'View Booking Details',
            'related_entity' => 'payment',
            'related_id'     => $this->payment->id,
            'priority'       => 'normal',
            'icon'           => 'check-circle-2',
            'metadata'       => [
                'payment_id' => $this->payment->id,
                'booking_id' => $this->payment->booking_id,
                'amount'     => (float) $this->payment->amount,
            ],
        ];
    }
}

