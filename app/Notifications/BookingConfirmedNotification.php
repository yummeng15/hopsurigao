<?php

namespace App\Notifications;

use App\Models\Booking;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class BookingConfirmedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Booking $booking
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
        $this->booking->loadMissing(['tourDate.package']);

        $packageName = $this->booking->tourDate?->package?->package_name ?? 'Tour Package';
        $tourDate = $this->booking->tourDate?->tour_date ? Carbon::parse($this->booking->tourDate->tour_date)->format('M d, Y') : 'Scheduled Date';

        return [
            'type'           => 'booking_confirmed',
            'title'          => 'Booking Confirmed! 🎉',
            'message'        => "Your booking for {$packageName} on {$tourDate} has been confirmed. You can now view your itinerary and QR ticket.",
            'action_url'     => "/user/bookings/{$this->booking->id}",
            'action_text'    => 'View Booking & QR Ticket',
            'related_entity' => 'booking',
            'related_id'     => $this->booking->id,
            'priority'       => 'normal',
            'icon'           => 'calendar-check',
            'metadata'       => [
                'booking_id'       => $this->booking->id,
                'package_name'     => $packageName,
                'destination'      => $this->booking->tourDate?->package?->destination,
                'tour_date'        => $tourDate,
                'number_of_guests' => $this->booking->number_of_guests,
                'qr_token'         => $this->booking->qr_token,
            ],
        ];
    }
}

