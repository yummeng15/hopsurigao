<?php

namespace App\Services\Reports;

use App\Models\PickupSchedule;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class PickupScheduleReportService extends BaseReportService
{
    public function getReportSlug(): string
    {
        return 'pickup-schedule';
    }

    public function getReportTitle(): string
    {
        return 'Pickup Dispatch & Transfer Report';
    }

    public function getViewName(): string
    {
        return 'reports.pickup-schedule-report';
    }

    public function getData(array $filters): array
    {
        [$dateFrom, $dateTo, $periodLabel] = $this->parseDateRange($filters);

        $query = PickupSchedule::query()
            ->with([
                'pickupLocation:id,name,address',
                'tourDate.package:id,package_name,destination',
                'tourDate.bookings' => function ($q) {
                    $q->whereIn('booking_status', ['pending', 'confirmed'])
                        ->with('user:id,name,email');
                },
            ]);

        // Tour date range filter
        if ($dateFrom && $dateTo) {
            $query->whereHas('tourDate', function (Builder $q) use ($dateFrom, $dateTo) {
                $q->whereBetween('tour_date', [$dateFrom, $dateTo]);
            });
        }

        // Pickup Location filter
        if (!empty($filters['pickup_location_id']) && $filters['pickup_location_id'] !== 'all') {
            $query->where('pickup_location_id', $filters['pickup_location_id']);
        }

        // Package filter
        if (!empty($filters['package_id']) && $filters['package_id'] !== 'all') {
            $query->whereHas('tourDate', function (Builder $q) use ($filters) {
                $q->where('package_id', $filters['package_id']);
            });
        }

        $schedules = $query->get()->sortBy(function (PickupSchedule $ps) {
            return ($ps->tourDate?->tour_date?->format('Y-m-d') ?? '') . ' ' . $ps->pickup_time;
        });

        $totalGuests = 0;
        $records = [];

        foreach ($schedules as $ps) {
            // Get bookings specifically assigned to this pickup schedule
            $matchingBookings = $ps->tourDate?->bookings
                ->where('pickup_schedule_id', $ps->id) ?? collect();

            $guestsAtPoint = (int) $matchingBookings->sum('number_of_guests');
            $totalGuests += $guestsAtPoint;

            $passengers = $matchingBookings->map(function ($b) {
                return [
                    'booking_id' => $b->id,
                    'lead_name' => $b->user?->name ?? 'Guest User',
                    'phone_number' => $b->phone_number,
                    'guests' => $b->number_of_guests,
                    'status' => $b->booking_status,
                ];
            })->values()->toArray();

            $records[] = [
                'id' => $ps->id,
                'location_name' => $ps->pickupLocation?->name ?? 'Default Pickup',
                'location_address' => $ps->pickupLocation?->address ?? 'Surigao Port Area',
                'tour_date' => $ps->tourDate?->tour_date?->format('M d, Y') ?? 'N/A',
                'pickup_time' => $ps->pickup_time ? Carbon::parse($ps->pickup_time)->format('h:i A') : 'N/A',
                'package_name' => $ps->tourDate?->package?->package_name ?? 'N/A',
                'destination' => $ps->tourDate?->package?->destination ?? 'Surigao',
                'guests_count' => $guestsAtPoint,
                'passengers' => $passengers,
            ];
        }

        $summary = [
            'total_pickup_runs' => count($records),
            'total_guests_to_pickup' => $totalGuests,
            'distinct_locations_count' => count(array_unique(array_column($records, 'location_name'))),
        ];

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

