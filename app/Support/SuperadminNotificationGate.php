<?php

namespace App\Support;

use App\Models\User;
use App\Notifications\BookingCancelledNotification;
use App\Notifications\BookingConfirmedNotification;
use App\Notifications\BookingCreatedNotification;
use App\Notifications\BookingFeedbackSubmittedNotification;
use App\Notifications\PaymentConfirmedNotification;
use App\Notifications\PaymentRejectedNotification;
use App\Notifications\PaymentSubmittedNotification;
use App\Notifications\WeatherAdvisoryNotification;

class SuperadminNotificationGate
{
    /**
     * Optional in-app categories a Super Admin may turn off.
     * Dangerous weather alerts are not included.
     *
     * @var list<string>
     */
    public const CATEGORIES = [
        'bookings',
        'cancellations',
        'payments',
        'weather',
    ];

    /**
     * Decide whether this notification should be stored for the account.
     * Essential booking, payment, and safety alerts are always delivered.
     */
    public static function shouldDeliver(object $notifiable, object $notification): bool
    {
        if (! $notifiable instanceof User) {
            return true;
        }

        $category = self::optionalCategory($notification, $notifiable->role?->name);

        if ($category === null) {
            return true;
        }

        return $notifiable->allowsOptionalNotification($category);
    }

    /**
     * Map a notification to an optional category for this role.
     * Null means the alert is essential and cannot be disabled.
     */
    public static function optionalCategory(object $notification, ?string $role = null): ?string
    {
        if ($role === 'Admin') {
            return match ($notification::class) {
                BookingCreatedNotification::class,
                BookingFeedbackSubmittedNotification::class => 'bookings',
                BookingCancelledNotification::class => 'trips',
                default => null,
            };
        }

        if ($role === 'User') {
            return $notification instanceof BookingCancelledNotification
                ? 'cancellations'
                : null;
        }

        if ($notification instanceof WeatherAdvisoryNotification) {
            if (in_array($notification->advisoryLevel, ['red', 'orange'], true)) {
                return null;
            }

            return 'weather';
        }

        return match ($notification::class) {
            BookingCreatedNotification::class,
            BookingConfirmedNotification::class,
            BookingFeedbackSubmittedNotification::class => 'bookings',
            BookingCancelledNotification::class => 'cancellations',
            PaymentConfirmedNotification::class, PaymentSubmittedNotification::class, PaymentRejectedNotification::class => 'payments',
            default => null,
        };
    }
}
