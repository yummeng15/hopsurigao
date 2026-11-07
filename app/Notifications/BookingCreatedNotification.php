<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class BookingCreatedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Booking $booking
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Notification Delivery
    |--------------------------------------------------------------------------
    |
    | Superadmin:
    |   Receives notifications from all operators.
    |
    | Admin:
    |   Receives notifications only for their own operator.
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
    | Notification Data
    |--------------------------------------------------------------------------
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
            ? Carbon::parse(
                $this->booking->tourDate->tour_date
            )->format('M d, Y')
            : 'Scheduled Date';

        $guests = $this->booking->number_of_guests;

        $role = $notifiable instanceof User
            ? $notifiable->role?->name
            : null;

        $actionUrl = $role === 'Admin'
            ? "/operator/bookings/{$this->booking->id}"
            : "/admin/bookings/{$this->booking->id}";

        return [
            'type' => 'booking_created',

            'title' => 'New Booking Submitted',

            'message' => "A new booking for {$guests} guest(s) "
                ."has been submitted by {$customerName} "
                ."for {$packageName} on {$tourDate}.",

            'action_url' => $actionUrl,

            'action_text' => 'View Booking',

            'related_entity' => 'booking',

            'related_id' => $this->booking->id,

            'priority' => 'normal',

            'icon' => 'calendar-plus',

            'metadata' => [
                'booking_id' => $this->booking->id,

                'customer_name' => $customerName,

                'customer_email' => $this->booking->user?->email,

                'package_name' => $packageName,

                'destination' => $this->booking
                    ->tourDate
                    ?->package
                    ?->destination,

                'tour_date' => $tourDate,

                'number_of_guests' => $guests,
            ],
        ];
    }
}
