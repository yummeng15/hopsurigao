<?php

namespace App\Services\Reports;

use InvalidArgumentException;

class ReportManager
{
    /**
     * @var array<string, class-string<BaseReportService>>
     */
    protected array $services = [
        'booking' => BookingReportService::class,
        'tour-schedule' => TourScheduleReportService::class,
        'payment' => PaymentReportService::class,
        'tourist' => TouristReportService::class,
        'pickup-schedule' => PickupScheduleReportService::class,
        'package-performance' => PackagePerformanceReportService::class,
        'weather-advisory' => WeatherAdvisoryReportService::class,
    ];

    /**
     * Get list of registered report definitions.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAvailableReports(): array
    {
        return [
            [
                'id' => 'booking',
                'title' => 'Booking Report',
                'description' => 'Comprehensive manifestation of bookings, reservation statuses, guests, contact details, and pickup points.',
                'icon' => 'ClipboardList',
                'supported_filters' => ['date_from', 'date_to', 'package_id', 'booking_status', 'pickup_location_id', 'nationality'],
            ],
            [
                'id' => 'tour-schedule',
                'title' => 'Tour Schedule Report',
                'description' => 'Daily operational schedule of tours with boat capacities, booked guests, occupancy rates, and departure times.',
                'icon' => 'CalendarClock',
                'supported_filters' => ['date_from', 'date_to', 'package_id'],
            ],
            [
                'id' => 'payment',
                'title' => 'Payment & Revenue Report',
                'description' => 'Financial transaction audit showing collected revenues, payment methods, transaction reference numbers, and pending payments.',
                'icon' => 'CreditCard',
                'supported_filters' => ['date_from', 'date_to', 'payment_status', 'payment_method', 'package_id'],
            ],
            [
                'id' => 'tourist',
                'title' => 'Tourist & Guest Manifest',
                'description' => 'Passenger demographics, nationality breakdown, party sizes, and contact roster for tourism authority reporting.',
                'icon' => 'Users',
                'supported_filters' => ['date_from', 'date_to', 'nationality', 'package_id', 'booking_status'],
            ],
            [
                'id' => 'pickup-schedule',
                'title' => 'Pickup Schedule Report',
                'description' => 'Driver dispatch manifest grouped by pickup locations, schedule times, and passenger headcounts.',
                'icon' => 'MapPin',
                'supported_filters' => ['date_from', 'date_to', 'pickup_location_id', 'package_id'],
            ],
            [
                'id' => 'package-performance',
                'title' => 'Package Performance Report',
                'description' => 'Comparative popularity analytics, sales volumes, conversion rates, and revenue generation per island package.',
                'icon' => 'TrendingUp',
                'supported_filters' => ['date_from', 'date_to', 'package_id', 'status'],
            ],
            [
                'id' => 'weather-advisory',
                'title' => 'Weather Advisory Report',
                'description' => 'Maritime safety forecasts, sea state assessments, wind/rain conditions, and affected guest counts for scheduled tours.',
                'icon' => 'CloudSun',
                'supported_filters' => ['date_from', 'date_to', 'advisory_level', 'package_id'],
            ],
        ];
    }

    /**
     * Resolve the service for the given report type.
     *
     * @throws InvalidArgumentException
     */
    public function resolveService(string $type): BaseReportService
    {
        if (!isset($this->services[$type])) {
            throw new InvalidArgumentException("Report type [{$type}] is not supported.");
        }

        $serviceClass = $this->services[$type];

        return app($serviceClass);
    }

    /**
     * Get report data array.
     */
    public function getData(string $type, array $filters): array
    {
        return $this->resolveService($type)->getData($filters);
    }

    /**
     * Generate PDF instance.
     */
    public function generatePdf(string $type, array $filters, ?string $generatedBy = null)
    {
        return $this->resolveService($type)->generatePdf($filters, $generatedBy);
    }

    /**
     * Get download filename.
     */
    public function getFilename(string $type, array $filters): string
    {
        return $this->resolveService($type)->getFilename($filters);
    }
}

