<?php

namespace App\Notifications;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PaymentRejectedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Payment $payment,
        public ?string $reason = null
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
        $reasonText = $this->reason ?: ($this->payment->notes ?: 'Please re-submit with a clear and valid payment receipt.');

        return [
            'type'           => 'payment_rejected',
            'title'          => 'Payment Needs Attention',
            'message'        => "Your payment submission for booking #{$this->payment->booking_id} could not be verified. Note: {$reasonText}",
            'action_url'     => "/user/bookings/{$this->payment->booking_id}/payment",
            'action_text'    => 'Resubmit Payment',
            'related_entity' => 'payment',
            'related_id'     => $this->payment->id,
            'priority'       => 'high',
            'icon'           => 'alert-circle',
            'metadata'       => [
                'payment_id' => $this->payment->id,
                'booking_id' => $this->payment->booking_id,
                'reason'     => $reasonText,
            ],
        ];
    }
}

