<?php

namespace App\Services\Reports;

use App\Models\Booking;
use Illuminate\Database\Eloquent\Builder;

class BookingReportService extends BaseReportService
{
    public function getReportSlug(): string
    {
        return 'booking';
    }

    public function getReportTitle(): string
    {
        return 'Booking & Reservation Report';
    }

    public function getViewName(): string
    {
        return 'reports.booking-report';
    }

    public function getData(array $filters): array
    {
        [$dateFrom, $dateTo, $periodLabel] = $this->parseDateRange($filters);

        $query = Booking::query()
            ->with([
                'user:id,name,email',
                'tourDate.package:id,package_name,destination,price',
                'pickupSchedule.pickupLocation:id,name',
                'payments:id,booking_id,amount,payment_status,payment_method,paid_at',
            ]);

        // Filter by tour date range
        if ($dateFrom && $dateTo) {
            $query->whereHas('tourDate', function (Builder $q) use ($dateFrom, $dateTo) {
                $q->whereBetween('tour_date', [$dateFrom, $dateTo]);
            });
        }

        if (! empty($filters['tour_operator_id'])) {
            $operatorId = (int) $filters['tour_operator_id'];
            $query->whereHas('tourDate.package', function (Builder $q) use ($operatorId) {
                $q->where('tour_operator_id', $operatorId);
            });
        }

        // Filter by package
        if (! empty($filters['package_id']) && $filters['package_id'] !== 'all') {
            $query->whereHas('tourDate', function (Builder $q) use ($filters) {
                $q->where('package_id', $filters['package_id']);
            });
        }

        // Filter by booking status
        if (! empty($filters['booking_status']) && $filters['booking_status'] !== 'all') {
            $query->where('booking_status', $filters['booking_status']);
        }

        // Filter by pickup location
        if (! empty($filters['pickup_location_id']) && $filters['pickup_location_id'] !== 'all') {
            $query->whereHas('pickupSchedule', function (Builder $q) use ($filters) {
                $q->where('pickup_location_id', $filters['pickup_location_id']);
            });
        }

        // Filter by nationality
        if (! empty($filters['nationality']) && $filters['nationality'] !== 'all') {
            $query->where('nationality', $filters['nationality']);
        }

        $bookings = $query->orderBy(
            Booking::select('tour_date')
                ->from('tour_dates')
                ->whereColumn('tour_dates.id', 'bookings.tour_date_id')
                ->limit(1)
        )->get();

        $summary = [
            'total_bookings' => $bookings->count(),
            'confirmed_bookings' => $bookings->where('booking_status', 'confirmed')->count(),
            'pending_bookings' => $bookings->where('booking_status', 'pending')->count(),
            'cancelled_bookings' => $bookings->where('booking_status', 'cancelled')->count(),
            'completed_bookings' => $bookings->where('booking_status', 'completed')->count(),
            'total_guests' => (int) $bookings->sum('number_of_guests'),
        ];

        $records = $bookings->map(function (Booking $b) {
            $latestPayment = $b->payments->sortByDesc('id')->first();

            return [
                'id' => $b->id,
                'tourist_name' => $b->user?->name ?? 'Guest User',
                'tourist_email' => $b->user?->email ?? 'N/A',
                'phone_number' => $b->phone_number,
                'nationality' => $b->nationality,
                'package_name' => $b->tourDate?->package?->package_name ?? 'N/A',
                'destination' => $b->tourDate?->package?->destination ?? 'Surigao',
                'tour_date' => $b->tourDate?->tour_date?->format('M d, Y') ?? 'N/A',
                'pickup_location' => $b->pickupSchedule?->pickupLocation?->name ?? 'N/A',
                'pickup_time' => $b->pickupSchedule?->pickup_time ?? 'N/A',
                'number_of_guests' => $b->number_of_guests,
                'booking_status' => $b->booking_status,
                'payment_status' => $latestPayment?->payment_status ?? 'unpaid',
                'payment_amount' => $latestPayment?->amount ?? null,
                'created_at' => $b->created_at->format('M d, Y'),
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
