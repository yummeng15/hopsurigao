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
        return 'Bookings per Package Report';
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
                'tourOperator:id,name',
                'tourDates.bookings.payments',
            ]);

        if (! empty($filters['tour_operator_id'])) {
            $query->where('tour_operator_id', (int) $filters['tour_operator_id']);
        }

        if (! empty($filters['package_id']) && $filters['package_id'] !== 'all') {
            $query->where('id', $filters['package_id']);
        }

        if (! empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        $packages = $query->orderBy('package_name')->get();

        $records = [];
        $overallBookings = 0;
        $overallGuests = 0;
        $overallRevenue = 0.0;
        $overallGcash = 0.0;
        $overallCash = 0.0;

        foreach ($packages as $pkg) {
            $allBookings = $pkg->tourDates
                ->flatMap->bookings
                ->unique('id')
                ->values();

            if ($dateFrom && $dateTo) {
                $allBookings = $allBookings->filter(function ($booking) use ($dateFrom, $dateTo) {
                    $created = $booking->created_at?->toDateString();

                    return $created !== null
                        && $created >= $dateFrom
                        && $created <= $dateTo;
                })->values();
            }

            $totalBookings = $allBookings->count();
            $pendingBookings = $allBookings->where('booking_status', 'pending')->count();
            $confirmedBookings = $allBookings->where('booking_status', 'confirmed')->count();
            $completedBookings = $allBookings->where('booking_status', 'completed')->count();
            $cancelledBookings = $allBookings->where('booking_status', 'cancelled')->count();
            $totalGuests = (int) $allBookings
                ->whereIn('booking_status', ['pending', 'confirmed', 'completed'])
                ->sum('number_of_guests');

            $gcashRevenue = 0.0;
            $cashRevenue = 0.0;

            foreach ($allBookings as $booking) {
                foreach ($booking->payments as $payment) {
                    if (! in_array($payment->payment_status, ['paid', 'partial'], true)) {
                        continue;
                    }

                    $amount = (float) $payment->amount;
                    $method = strtolower((string) $payment->payment_method);
                    $gateway = strtolower((string) $payment->gateway);

                    if ($gateway === 'paymongo' || $method === 'gcash') {
                        $gcashRevenue += $amount;
                    } else {
                        $cashRevenue += $amount;
                    }
                }
            }

            $revenue = round($gcashRevenue + $cashRevenue, 2);

            $conversionRate = $totalBookings > 0
                ? round((($confirmedBookings + $completedBookings) / $totalBookings) * 100, 1)
                : 0;

            $overallBookings += $totalBookings;
            $overallGuests += $totalGuests;
            $overallRevenue += $revenue;
            $overallGcash += $gcashRevenue;
            $overallCash += $cashRevenue;

            $records[] = [
                'id' => $pkg->id,
                'package_name' => $pkg->package_name,
                'operator_name' => $pkg->tourOperator?->name ?? 'Unassigned',
                'destination' => $pkg->destination,
                'price' => (float) $pkg->price,
                'status' => $pkg->status,
                'total_bookings' => $totalBookings,
                'pending_bookings' => $pendingBookings,
                'confirmed_bookings' => $confirmedBookings,
                'completed_bookings' => $completedBookings,
                'cancelled_bookings' => $cancelledBookings,
                'total_guests' => $totalGuests,
                'revenue' => $revenue,
                'gcash_revenue' => round($gcashRevenue, 2),
                'cash_revenue' => round($cashRevenue, 2),
                'conversion_rate' => $conversionRate,
            ];
        }

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
            'overall_pending_bookings' => (int) collect($records)->sum('pending_bookings'),
            'overall_confirmed_bookings' => (int) collect($records)->sum('confirmed_bookings'),
            'overall_completed_bookings' => (int) collect($records)->sum('completed_bookings'),
            'overall_cancelled_bookings' => (int) collect($records)->sum('cancelled_bookings'),
            'overall_total_guests' => $overallGuests,
            'overall_total_revenue' => round($overallRevenue, 2),
            'overall_gcash_revenue' => round($overallGcash, 2),
            'overall_cash_revenue' => round($overallCash, 2),
            'most_booked_package' => $topPackageByBookings,
            'highest_revenue_package' => $topPackageByRevenue,
        ];

        return [
            'period_label' => $periodLabel,
            'summary' => $summary,
            'records' => $records,
            'meta' => [
                'filters_applied' => array_filter($filters),
                'note' => 'Booking statuses counted separately: pending, confirmed, completed, cancelled. Each booking is counted once. GCash amounts are PayMongo-verified payments; cash amounts are walk-in/operator receipts.',
            ],
        ];
    }
}
