<?php

namespace App\Notifications;

use App\Models\RefundRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class RefundDecisionNotification extends Notification
{
    use Queueable;

    public function __construct(public RefundRequest $refundRequest) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $label = $this->refundRequest->statusLabel();
        $bookingId = $this->refundRequest->booking_id;
        $isRejected = $this->refundRequest->status === RefundRequest::STATUS_REJECTED;

        return [
            'type' => 'refund_decision',
            'title' => $isRejected ? 'Refund request rejected' : 'Refund update',
            'message' => $isRejected
                ? "Your refund request for booking #{$bookingId} was rejected."
                : "Your refund request for booking #{$bookingId} is now: {$label}.",
            'action_url' => "/user/bookings/{$bookingId}",
            'action_text' => 'View Booking',
            'related_entity' => 'refund_request',
            'related_id' => $this->refundRequest->id,
            'priority' => 'normal',
            'icon' => $isRejected ? 'circle-x' : 'badge-check',
            'metadata' => [
                'refund_request_id' => $this->refundRequest->id,
                'booking_id' => $bookingId,
                'status' => $this->refundRequest->status,
                'status_label' => $label,
            ],
        ];
    }
}
