<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Models\BookingFeedback;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class BookingFeedbackSubmittedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Booking $booking,
        public BookingFeedback $feedback
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        if (
            $notifiable instanceof User &&
            $notifiable->role?->name === 'Admin'
        ) {
            $operator = $notifiable->tourOperator;

            $bookingOperatorId = $this->booking
                ->tourDate
                ?->package
                ?->tour_operator_id;

            if (
                $operator === null ||
                ! $operator->is_active ||
                $bookingOperatorId === null ||
                (int) $operator->id !== (int) $bookingOperatorId
            ) {
                return [];
            }
        }

        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $this->booking->loadMissing([
            'user',
            'tourDate.package',
        ]);

        $customerName = $this->booking->user?->name ?? 'Guest';

        $packageName = $this->booking
            ->tourDate
            ?->package
            ?->package_name ?? 'Tour Package';

        $tourDate = $this->booking->tourDate?->tour_date
            ? Carbon::parse($this->booking->tourDate->tour_date)->format('M d, Y')
            : 'Tour date';

        $role = $notifiable instanceof User
            ? $notifiable->role?->name
            : null;

        $actionUrl = $role === 'Admin'
            ? '/operator/operations/feedback'
            : '/admin/booking-feedback';

        return [
            'type' => 'booking_feedback_submitted',
            'title' => 'New Tour Feedback',
            'message' => "{$customerName} left feedback for {$packageName} on {$tourDate}.",
            'action_url' => $actionUrl,
            'action_text' => 'View Feedback',
            'related_entity' => 'booking_feedback',
            'related_id' => $this->feedback->id,
            'priority' => 'normal',
            'icon' => 'message-square',
            'metadata' => [
                'booking_id' => $this->booking->id,
                'feedback_id' => $this->feedback->id,
                'customer_name' => $customerName,
                'package_name' => $packageName,
                'tour_date' => $tourDate,
                'enjoyment' => $this->feedback->enjoyment,
                'operator_service' => $this->feedback->operator_service,
                'overall_experience' => $this->feedback->overall_experience,
            ],
        ];
    }
}
