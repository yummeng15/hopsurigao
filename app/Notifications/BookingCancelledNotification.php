<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class BookingCancelledNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Booking $booking,
        public ?string $cancelledBy = null
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Notification Delivery
    |--------------------------------------------------------------------------
    |
    | Superadmin:
    | Receives cancellation notifications for all operators.
    |
    | Admin:
    | Receives cancellation notifications only when the booking
    | belongs to their assigned tour operator.
    |
    | Tourist:
    | Can still receive cancellation notifications for their booking.
    |
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

    /*
    |--------------------------------------------------------------------------
    | Notification Content
    |--------------------------------------------------------------------------
    */

    public function toArray(object $notifiable): array
    {
        $this->booking->loadMissing([
            'user',
            'tourDate.package',
        ]);

        $packageName = $this->booking
            ->tourDate
            ?->package
            ?->package_name ?? 'Tour Package';

        $tourDate = $this->booking->tourDate?->tour_date
            ? Carbon::parse(
                $this->booking->tourDate->tour_date
            )->format('M d, Y')
            : 'Scheduled Date';

        $role = $notifiable instanceof User
            ? $notifiable->role?->name
            : null;

        $isStaff = in_array($role, ['Admin', 'Superadmin'], true);

        $actionUrl = match ($role) {
            'Admin' => "/operator/bookings/{$this->booking->id}",
            'Superadmin' => "/admin/bookings/{$this->booking->id}",
            default => "/user/bookings/{$this->booking->id}",
        };

        $customerName = $this->booking->user?->name ?? 'Guest';

        $message = $isStaff
            ? "Booking #{$this->booking->id} for {$packageName} "
                ."on {$tourDate} (Guest: {$customerName}) has been cancelled."
            : "Your booking for {$packageName} "
                ."on {$tourDate} has been cancelled.";

        return [
            'type' => 'booking_cancelled',

            'title' => 'Booking Cancelled',

            'message' => $message,

            'action_url' => $actionUrl,

            'action_text' => 'View Bookings',

            'related_entity' => 'booking',

            'related_id' => $this->booking->id,

            'priority' => 'high',

            'icon' => 'calendar-x',

            'metadata' => [
                'booking_id' => $this->booking->id,

                'package_name' => $packageName,

                'destination' => $this->booking
                    ->tourDate
                    ?->package
                    ?->destination,

                'tour_date' => $tourDate,

                'cancelled_by' => $this->cancelledBy,
            ],
        ];
    }
}
