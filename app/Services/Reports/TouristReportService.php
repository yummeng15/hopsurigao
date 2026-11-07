<?php

namespace App\Services\Reports;

use App\Models\Booking;
use Illuminate\Database\Eloquent\Builder;

class TouristReportService extends BaseReportService
{
    public function getReportSlug(): string
    {
        return 'tourist';
    }

    public function getReportTitle(): string
    {
        return 'Tourist & Guest Manifest Report';
    }

    public function getViewName(): string
    {
        return 'reports.tourist-report';
    }

    public function getData(array $filters): array
    {
        [$dateFrom, $dateTo, $periodLabel] = $this->parseDateRange($filters);

        $query = Booking::query()
            ->with([
                'user:id,name,email',
                'tourDate.package:id,package_name,destination',
                'pickupSchedule.pickupLocation:id,name',
            ]);

        // Tour date range filter
        if ($dateFrom && $dateTo) {
            $query->whereHas('tourDate', function (Builder $q) use ($dateFrom, $dateTo) {
                $q->whereBetween('tour_date', [$dateFrom, $dateTo]);
            });
        }

        // Nationality filter
        if (!empty($filters['nationality']) && $filters['nationality'] !== 'all') {
            $query->where('nationality', $filters['nationality']);
        }

        // Package filter
        if (!empty($filters['package_id']) && $filters['package_id'] !== 'all') {
            $query->whereHas('tourDate', function (Builder $q) use ($filters) {
                $q->where('package_id', $filters['package_id']);
            });
        }

        // Status filter
        if (!empty($filters['booking_status']) && $filters['booking_status'] !== 'all') {
            $query->where('booking_status', $filters['booking_status']);
        }

        $bookings = $query->orderBy('created_at', 'desc')->get();

        // Nationality breakdown
        $nationalities = [];
        foreach ($bookings->groupBy('nationality') as $nat => $group) {
            $natKey = $nat ?: 'Unspecified';
            $nationalities[$natKey] = [
                'bookings_count' => $group->count(),
                'guests_count' => (int) $group->sum('number_of_guests'),
            ];
        }

        uasort($nationalities, fn($a, $b) => $b['guests_count'] <=> $a['guests_count']);

        $summary = [
            'total_lead_travelers' => $bookings->count(),
            'total_guests' => (int) $bookings->sum('number_of_guests'),
            'active_guests' => (int) $bookings->whereIn('booking_status', ['confirmed', 'pending'])->sum('number_of_guests'),
            'nationalities_count' => count($nationalities),
            'nationalities_breakdown' => $nationalities,
        ];

        $records = $bookings->map(function (Booking $b) {
            return [
                'id' => $b->id,
                'tourist_name' => $b->user?->name ?? 'Guest User',
                'tourist_email' => $b->user?->email ?? 'N/A',
                'phone_number' => $b->phone_number,
                'nationality' => $b->nationality,
                'package_name' => $b->tourDate?->package?->package_name ?? 'N/A',
                'destination' => $b->tourDate?->package?->destination ?? 'Surigao',
                'tour_date' => $b->tourDate?->tour_date?->format('M d, Y') ?? 'N/A',
                'number_of_guests' => $b->number_of_guests,
                'pickup_location' => $b->pickupSchedule?->pickupLocation?->name ?? 'N/A',
                'special_request' => $b->special_request ?? 'None',
                'booking_status' => $b->booking_status,
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

