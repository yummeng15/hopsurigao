<?php

namespace App\Services;

use App\Models\Package;
use App\Models\TourDate;
use App\Models\TourOperator;

/**
 * Resolve tourist-facing booking status and exact unavailable reasons.
 */
class TourDateBookingAvailability
{
    public const STATUS_AVAILABLE = 'available';

    public const STATUS_FULLY_BOOKED = 'fully_booked';

    public const STATUS_UNDER_MAINTENANCE = 'under_maintenance';

    public const STATUS_TRAVEL_CLOSED_WEATHER = 'travel_closed_weather';

    public const STATUS_TRAVEL_CLOSED_MANUAL = 'travel_closed_manual';

    public const STATUS_OPERATOR_UNAVAILABLE = 'operator_unavailable';

    public const STATUS_NO_SCHEDULE = 'no_schedule';

    public function __construct(
        private WeatherAdvisoryService $weather,
    ) {}

    /**
     * @return array{
     *     status: string,
     *     label: string,
     *     reason: string|null,
     *     bookable: bool,
     *     forecast_notice: string|null,
     *     remaining_capacity: int,
     *     is_full: bool,
     *     booking_closed: bool,
     *     booking_closed_reason: string|null
     * }
     */
    public function assess(
        Package $package,
        TourDate $tourDate,
        int $bookedGuests,
        ?string $advisoryLevel,
        bool $hasForecastRow = true,
        ?bool $hasCompleteSchedule = null,
    ): array {
        $remaining = max(0, (int) $tourDate->capacity - $bookedGuests);
        $isFull = $remaining <= 0;
        $forecastNotice = null;

        if (! $hasForecastRow || $advisoryLevel === null || $advisoryLevel === '' || $advisoryLevel === 'unavailable') {
            $forecastNotice = 'Weather forecast is not yet available for this date. Conditions are not marked safe or unsafe until Open-Meteo data is received.';
        }

        if ($hasCompleteSchedule === null) {
            $tourDate->loadMissing(['times', 'pickupSchedules']);
            $hasCompleteSchedule = $tourDate->times->isNotEmpty()
                && $tourDate->pickupSchedules->isNotEmpty();
        }

        if (! $hasCompleteSchedule) {
            return $this->result(
                status: self::STATUS_NO_SCHEDULE,
                label: 'No Schedule Available',
                reason: 'No Schedule Available: This tour has no departure scheduled for this date. Please choose another available date.',
                bookable: false,
                remaining: $remaining,
                isFull: $isFull,
                forecastNotice: $forecastNotice,
            );
        }

        if ((bool) $package->under_maintenance) {
            return $this->result(
                status: self::STATUS_UNDER_MAINTENANCE,
                label: 'Under Maintenance',
                reason: 'The assigned boat is currently under maintenance.',
                bookable: false,
                remaining: $remaining,
                isFull: $isFull,
                forecastNotice: $forecastNotice,
            );
        }

        $package->loadMissing('tourOperator');
        $operator = $package->tourOperator;

        if (! $operator instanceof TourOperator || ! $operator->acceptsBookings()) {
            return $this->result(
                status: self::STATUS_OPERATOR_UNAVAILABLE,
                label: 'Unavailable',
                reason: 'This tour is not available until its operator is approved.',
                bookable: false,
                remaining: $remaining,
                isFull: $isFull,
                forecastNotice: $forecastNotice,
            );
        }

        if ($isFull) {
            return $this->result(
                status: self::STATUS_FULLY_BOOKED,
                label: 'Fully Booked',
                reason: 'All guest slots have already been reserved.',
                bookable: false,
                remaining: 0,
                isFull: true,
                forecastNotice: $forecastNotice,
            );
        }

        if ($this->weather->blocksTravel($advisoryLevel)) {
            return $this->result(
                status: self::STATUS_TRAVEL_CLOSED_WEATHER,
                label: 'Travel Closed',
                reason: 'Travel Closed: Unfavorable weather conditions. Booking is temporarily unavailable for this date. Please choose another available date.',
                bookable: false,
                remaining: $remaining,
                isFull: false,
                forecastNotice: $forecastNotice,
            );
        }

        if ((bool) $tourDate->travel_closed) {
            return $this->result(
                status: self::STATUS_TRAVEL_CLOSED_MANUAL,
                label: 'Travel Closed',
                reason: 'This tour date has been closed by the tour operator.',
                bookable: false,
                remaining: $remaining,
                isFull: false,
                forecastNotice: $forecastNotice,
            );
        }

        return $this->result(
            status: self::STATUS_AVAILABLE,
            label: 'Available',
            reason: null,
            bookable: true,
            remaining: $remaining,
            isFull: false,
            forecastNotice: $forecastNotice,
        );
    }

    /**
     * @return array{
     *     status: string,
     *     label: string,
     *     reason: string|null,
     *     bookable: bool,
     *     forecast_notice: string|null,
     *     remaining_capacity: int,
     *     is_full: bool,
     *     booking_closed: bool,
     *     booking_closed_reason: string|null
     * }
     */
    private function result(
        string $status,
        string $label,
        ?string $reason,
        bool $bookable,
        int $remaining,
        bool $isFull,
        ?string $forecastNotice,
    ): array {
        return [
            'status' => $status,
            'label' => $label,
            'reason' => $reason,
            'bookable' => $bookable,
            'forecast_notice' => $forecastNotice,
            'remaining_capacity' => $remaining,
            'is_full' => $isFull,
            'booking_closed' => ! $bookable && $status !== self::STATUS_FULLY_BOOKED,
            'booking_closed_reason' => $bookable ? null : $reason,
        ];
    }
}
