<?php

namespace App\Services\Reports;

use App\Models\Package;

class PackagePerformanceReportService extends BaseReportService
{
    public function getReportSlug(): string
    {
        return 'package-performance';
    }

    public function getReportTitle(): string
    {
        return 'Package Performance & Popularity Report';
    }

    public function getViewName(): string
    {
        return 'reports.package-performance-report';
    }

    public function getData(array $filters): array
    {
        [$dateFrom, $dateTo, $periodLabel] = $this->parseDateRange($filters);

        $query = Package::query()
            ->with([
                'tourDates' => function ($tdQuery) use ($dateFrom, $dateTo) {
                    if ($dateFrom && $dateTo) {
                        $tdQuery->whereBetween('tour_date', [$dateFrom, $dateTo]);
                    }
                    $tdQuery->with([
                        'bookings.payments',
                    ]);
                },
            ]);

        if (! empty($filters['package_id']) && $filters['package_id'] !== 'all') {
            $query->where('id', $filters['package_id']);
        }

        if (! empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        $packages = $query->get();

        $records = [];
        $overallBookings = 0;
        $overallGuests = 0;
        $overallRevenue = 0.0;

        foreach ($packages as $pkg) {
            $allBookings = $pkg->tourDates->flatMap->bookings;

            $totalBookings = $allBookings->count();
            $confirmedBookings = $allBookings->where('booking_status', 'confirmed')->count();
            $cancelledBookings = $allBookings->where('booking_status', 'cancelled')->count();
            $totalGuests = (int) $allBookings->whereIn('booking_status', ['pending', 'confirmed', 'completed'])->sum('number_of_guests');

            // Calculate verified revenue from paid/confirmed payments
            $allPayments = $allBookings->flatMap->payments;
            $revenue = (float) $allPayments->whereIn('payment_status', ['paid', 'partial', 'confirmed'])->sum('amount');

            $conversionRate = $totalBookings > 0
                ? round(($confirmedBookings / $totalBookings) * 100, 1)
                : 0;

            $overallBookings += $totalBookings;
            $overallGuests += $totalGuests;
            $overallRevenue += $revenue;

            $records[] = [
                'id' => $pkg->id,
                'package_name' => $pkg->package_name,
                'destination' => $pkg->destination,
                'price' => (float) $pkg->price,
                'status' => $pkg->status,
                'total_bookings' => $totalBookings,
                'confirmed_bookings' => $confirmedBookings,
                'cancelled_bookings' => $cancelledBookings,
                'total_guests' => $totalGuests,
                'revenue' => $revenue,
                'conversion_rate' => $conversionRate,
            ];
        }

        // Sort records by revenue desc
        usort($records, fn ($a, $b) => $b['revenue'] <=> $a['revenue']);

        $topPackageByBookings = count($records) > 0
            ? collect($records)->sortByDesc('total_bookings')->first()['package_name']
            : 'None';

        $topPackageByRevenue = count($records) > 0
            ? collect($records)->sortByDesc('revenue')->first()['package_name']
            : 'None';

        $summary = [
            'total_packages_count' => count($records),
            'overall_total_bookings' => $overallBookings,
            'overall_total_guests' => $overallGuests,
            'overall_total_revenue' => $overallRevenue,
            'most_booked_package' => $topPackageByBookings,
            'highest_revenue_package' => $topPackageByRevenue,
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
