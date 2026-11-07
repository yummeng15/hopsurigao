<?php

namespace App\Notifications;

use App\Models\TourDate;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class WeatherAdvisoryNotification extends Notification
{
    use Queueable;

    public function __construct(
        public TourDate $tourDate,
        public string $advisoryLevel,
        public string $advisoryBadge,
        public string $advisoryMessage,
        public ?string $previousLevel = null
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Notification Delivery Rules
    |--------------------------------------------------------------------------
    |
    | Superadmin:
    | Receives notifications for all operators.
    |
    | Admin:
    | Receives notifications only for their assigned operator.
    |
    | Tourist:
    | Existing notifications for their booked tours remain.
    |
    */

    public function via(object $notifiable): array
    {
        if (
            $notifiable instanceof User &&
            $notifiable->role?->name === 'Admin'
        ) {
            $operator = $notifiable->tourOperator;

            $forecastOperatorId = $this->tourDate
                ->package
                ?->tour_operator_id;

            if (
                $operator === null ||
                ! $operator->is_active ||
                $forecastOperatorId === null ||
                (int) $operator->id !== (int) $forecastOperatorId
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
        $this->tourDate->loadMissing('package');

        $packageName = $this->tourDate
            ->package
            ?->package_name ?? 'Island Hopping Tour';

        $destination = $this->tourDate
            ->package
            ?->destination ?? 'Destination';

        $dateStr = $this->tourDate->tour_date
            ? Carbon::parse(
                $this->tourDate->tour_date
            )->format('M d, Y')
            : 'Upcoming Tour';

        $isOperator = in_array(
            $notifiable->role?->name,
            ['Admin', 'Superadmin'],
            true
        );

        /*
        |--------------------------------------------------------------------------
        | Priority
        |--------------------------------------------------------------------------
        */

        $priority = match ($this->advisoryLevel) {
            'red' => 'urgent',
            'orange' => 'high',
            'yellow' => 'high',
            default => 'normal',
        };

        /*
        |--------------------------------------------------------------------------
        | Icon
        |--------------------------------------------------------------------------
        */

        $icon = match ($this->advisoryLevel) {
            'red' => 'cloud-lightning',
            'orange' => 'cloud-rain',
            'yellow' => 'cloud-sun',
            default => 'sun',
        };

        /*
        |--------------------------------------------------------------------------
        | Title
        |--------------------------------------------------------------------------
        */

        $title = match ($this->advisoryLevel) {
            'red' => '🚨 Severe Weather Alert',
            'orange' => '⚠️ Weather Advisory (Unfavorable)',
            'yellow' => '🌦️ Weather Caution Notice',
            default => '☀️ Favorable Weather Update',
        };

        /*
        |--------------------------------------------------------------------------
        | Destination URL
        |--------------------------------------------------------------------------
        */

        $actionUrl = $isOperator
            ? '/admin/weather-advisories'
            : '/user/bookings';

        /*
        |--------------------------------------------------------------------------
        | Message
        |--------------------------------------------------------------------------
        */

        $message =
            "Weather condition for {$packageName} "
            ."in {$destination} on {$dateStr} "
            ."is now rated [{$this->advisoryBadge}]. "
            .$this->advisoryMessage;

        /*
        |--------------------------------------------------------------------------
        | Notification Payload
        |--------------------------------------------------------------------------
        */

        return [
            'type' => $this->advisoryLevel === 'red'
                ? 'severe_weather'
                : 'weather_advisory',

            'title' => $title,

            'message' => $message,

            'action_url' => $actionUrl,

            'action_text' => $isOperator
                ? 'View Maritime Advisories'
                : 'View My Booking',

            'related_entity' => 'tour_date',

            'related_id' => $this->tourDate->id,

            'priority' => $priority,

            'icon' => $icon,

            'metadata' => [
                'tour_date_id' => $this->tourDate->id,

                'tour_date' => $dateStr,

                'package_name' => $packageName,

                'destination' => $destination,

                'advisory_level' => $this->advisoryLevel,

                'advisory_badge' => $this->advisoryBadge,

                'previous_level' => $this->previousLevel,
            ],
        ];
    }
}