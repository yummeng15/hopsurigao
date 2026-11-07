<?php

namespace App\Services\Reports;

use App\Models\TourDate;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class TourScheduleReportService extends BaseReportService
{
    public function getReportSlug(): string
    {
        return 'tour-schedule';
    }

    public function getReportTitle(): string
    {
        return 'Tour Schedule & Capacity Report';
    }

    public function getViewName(): string
    {
        return 'reports.tour-schedule-report';
    }

    public function getData(array $filters): array
    {
        [$dateFrom, $dateTo, $periodLabel] = $this->parseDateRange($filters);

        $query = TourDate::query()
            ->with([
                'package:id,package_name,destination,price,status',
                'times:id,tour_date_id,time,description',
                'pickupSchedules.pickupLocation:id,name,address',
                'bookings' => function ($q) {
                    $q->whereIn('booking_status', ['pending', 'confirmed']);
                },
                'weatherForecast',
            ]);

        // Date range filter
        if ($dateFrom && $dateTo) {
            $query->whereBetween('tour_date', [$dateFrom, $dateTo]);
        }

        // Package filter
        if (!empty($filters['package_id']) && $filters['package_id'] !== 'all') {
            $query->where('package_id', $filters['package_id']);
        }

        $schedules = $query->orderBy('tour_date', 'asc')->get();

        $totalCapacity = (int) $schedules->sum('capacity');
        $totalBookedGuests = (int) $schedules->sum(function (TourDate $td) {
            return $td->bookings->sum('number_of_guests');
        });
        $totalAvailableSlots = max(0, $totalCapacity - $totalBookedGuests);
        $avgOccupancy = $totalCapacity > 0 ? round(($totalBookedGuests / $totalCapacity) * 100, 1) : 0;

        $summary = [
            'total_schedules' => $schedules->count(),
            'total_capacity' => $totalCapacity,
            'total_booked_guests' => $totalBookedGuests,
            'total_available_slots' => $totalAvailableSlots,
            'average_occupancy_rate' => $avgOccupancy,
        ];

        $records = $schedules->map(function (TourDate $td) {
            $bookedGuests = (int) $td->bookings->sum('number_of_guests');
            $available = max(0, $td->capacity - $bookedGuests);
            $occupancyPercent = $td->capacity > 0 ? round(($bookedGuests / $td->capacity) * 100, 1) : 0;

            $timesList = $td->times->pluck('time')->map(function ($time) {
                return Carbon::parse($time)->format('h:i A');
            })->implode(', ');

            $pickupLocations = $td->pickupSchedules->map(function ($ps) {
                $name = $ps->pickupLocation?->name ?? 'Pickup';
                $time = $ps->pickup_time ? Carbon::parse($ps->pickup_time)->format('h:i A') : '';
                return $time ? "{$name} ({$time})" : $name;
            })->implode(', ');

            return [
                'id' => $td->id,
                'package_name' => $td->package?->package_name ?? 'N/A',
                'destination' => $td->package?->destination ?? 'Surigao',
                'tour_date' => $td->tour_date?->format('M d, Y') ?? 'N/A',
                'tour_date_raw' => $td->tour_date?->format('Y-m-d'),
                'capacity' => $td->capacity,
                'booked_guests' => $bookedGuests,
                'available_slots' => $available,
                'occupancy_percent' => $occupancyPercent,
                'times' => $timesList ?: 'Standard Departure',
                'pickup_points' => $pickupLocations ?: 'Direct at Pier',
                'weather_condition' => $td->weatherForecast?->weather_condition ?? 'N/A',
                'advisory_level' => $td->weatherForecast?->advisory_level ?? 'green',
                'advisory_badge' => $td->weatherForecast?->advisory_badge ?? 'Normal',
            ];
        })->toArray();

        return [
            'period_label' => $periodLabel,
            'summary' => $summary,
            'records' => $records,
            'meta' => [
                'filters_applied' => array_filter($filters),
            ],
        ];
    }
}

