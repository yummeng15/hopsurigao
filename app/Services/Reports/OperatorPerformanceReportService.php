<?php

namespace App\Services\Reports;

use App\Models\Booking;
use App\Models\TourOperator;

class OperatorPerformanceReportService extends BaseReportService
{
    public function getReportSlug(): string
    {
        return 'operator-performance';
    }

    public function getReportTitle(): string
    {
        return 'Bookings per Tour Operator Report';
    }

    public function getViewName(): string
    {
        return 'reports.operator-performance-report';
    }

    public function getData(array $filters): array
    {
        [$dateFrom, $dateTo, $periodLabel] = $this->parseDateRange($filters);

        $operatorsQuery = TourOperator::query()->orderBy('name');

        if (! empty($filters['tour_operator_id'])) {
            $operatorsQuery->where('id', (int) $filters['tour_operator_id']);
        }

        $operators = $operatorsQuery->get(['id', 'name', 'approval_status', 'is_active']);

        $records = [];
        $overallBookings = 0;
        $overallPending = 0;
        $overallConfirmed = 0;
        $overallCompleted = 0;
        $overallCancelled = 0;
        $overallRevenue = 0.0;
        $overallGcash = 0.0;
        $overallCash = 0.0;

        foreach ($operators as $operator) {
            $bookingsQuery = Booking::query()
                ->withoutGlobalScopes()
                ->whereHas(
                    'tourDate.package',
                    fn ($query) => $query->where('tour_operator_id', $operator->id)
                )
                ->with(['payments']);

            if ($dateFrom && $dateTo) {
                $bookingsQuery->whereBetween('created_at', [
                    $dateFrom.' 00:00:00',
                    $dateTo.' 23:59:59',
                ]);
            }

            $bookings = $bookingsQuery->get();

            $totalBookings = $bookings->count();
            $pendingBookings = $bookings->where('booking_status', 'pending')->count();
            $confirmedBookings = $bookings->where('booking_status', 'confirmed')->count();
            $completedBookings = $bookings->where('booking_status', 'completed')->count();
            $cancelledBookings = $bookings->where('booking_status', 'cancelled')->count();

            $gcashRevenue = 0.0;
            $cashRevenue = 0.0;

            foreach ($bookings as $booking) {
                foreach ($booking->payments as $payment) {
                    if (! in_array($payment->payment_status, ['paid', 'partial'], true)) {
                        continue;
                    }

                    $amount = (float) $payment->amount;
                    $method = strtolower((string) $payment->payment_method);
                    $gateway = strtolower((string) $payment->gateway);

                    if ($gateway === 'paymongo' || $method === 'gcash') {
                        $gcashRevenue += $amount;
                    } elseif ($method === 'cash') {
                        $cashRevenue += $amount;
                    } else {
                        $cashRevenue += $amount;
                    }
                }
            }

            $revenue = round($gcashRevenue + $cashRevenue, 2);

            $overallBookings += $totalBookings;
            $overallPending += $pendingBookings;
            $overallConfirmed += $confirmedBookings;
            $overallCompleted += $completedBookings;
            $overallCancelled += $cancelledBookings;
            $overallRevenue += $revenue;
            $overallGcash += $gcashRevenue;
            $overallCash += $cashRevenue;

            $records[] = [
                'operator_id' => $operator->id,
                'operator_name' => $operator->name,
                'approval_status' => $operator->approval_status,
                'total_bookings' => $totalBookings,
                'pending_bookings' => $pendingBookings,
                'confirmed_bookings' => $confirmedBookings,
                'completed_bookings' => $completedBookings,
                'cancelled_bookings' => $cancelledBookings,
                'revenue' => $revenue,
                'gcash_revenue' => round($gcashRevenue, 2),
                'cash_revenue' => round($cashRevenue, 2),
            ];
        }

        usort($records, fn (array $a, array $b): int => $b['total_bookings'] <=> $a['total_bookings']);

        return [
            'period_label' => $periodLabel,
            'summary' => [
                'total_operators' => count($records),
                'overall_total_bookings' => $overallBookings,
                'overall_pending_bookings' => $overallPending,
                'overall_confirmed_bookings' => $overallConfirmed,
                'overall_completed_bookings' => $overallCompleted,
                'overall_cancelled_bookings' => $overallCancelled,
                'overall_total_revenue' => round($overallRevenue, 2),
                'overall_gcash_revenue' => round($overallGcash, 2),
                'overall_cash_revenue' => round($overallCash, 2),
            ],
            'records' => $records,
            'meta' => [
                'filters_applied' => array_filter($filters),
                'note' => 'Booking statuses counted separately: pending, confirmed, completed, cancelled. Each booking is counted once even with multiple payment attempts. Revenue counts paid/partial payment rows.',
            ],
        ];
    }
}
