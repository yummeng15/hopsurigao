<?php

namespace App\Services\Reports;

use App\Models\Payment;
use Illuminate\Database\Eloquent\Builder;

class PaymentReportService extends BaseReportService
{
    public function getReportSlug(): string
    {
        return 'payment';
    }

    public function getReportTitle(): string
    {
        return 'Payment & Revenue Report';
    }

    public function getViewName(): string
    {
        return 'reports.payment-report';
    }

    public function getData(array $filters): array
    {
        [$dateFrom, $dateTo, $periodLabel] = $this->parseDateRange($filters);

        $query = Payment::query()
            ->with([
                'booking.user:id,name,email',
                'booking.tourDate.package:id,package_name,destination',
            ]);

        // Date range filter (paid_at or created_at)
        if ($dateFrom && $dateTo) {
            $query->where(function (Builder $q) use ($dateFrom, $dateTo) {
                $q->whereBetween('paid_at', [$dateFrom, $dateTo])
                    ->orWhere(function (Builder $sub) use ($dateFrom, $dateTo) {
                        $sub->whereNull('paid_at')
                            ->whereBetween('created_at', [$dateFrom, $dateTo]);
                    });
            });
        }

        // Status filter
        if (! empty($filters['payment_status']) && $filters['payment_status'] !== 'all') {
            $query->where('payment_status', $filters['payment_status']);
        }

        // Method filter
        if (! empty($filters['payment_method']) && $filters['payment_method'] !== 'all') {
            $query->where('payment_method', $filters['payment_method']);
        }

        if (! empty($filters['tour_operator_id'])) {
            $operatorId = (int) $filters['tour_operator_id'];
            $query->whereHas('booking.tourDate.package', function (Builder $q) use ($operatorId) {
                $q->where('tour_operator_id', $operatorId);
            });
        }

        // Package filter
        if (! empty($filters['package_id']) && $filters['package_id'] !== 'all') {
            $query->whereHas('booking.tourDate', function (Builder $q) use ($filters) {
                $q->where('package_id', $filters['package_id']);
            });
        }

        $payments = $query->orderBy('created_at', 'desc')->get();

        $verifiedPayments = $payments->whereIn('payment_status', ['paid', 'confirmed']);
        $pendingPayments = $payments->where('payment_status', 'pending');
        $rejectedPayments = $payments->where('payment_status', 'rejected');

        $totalRevenue = (float) $verifiedPayments->sum('amount')
            + (float) $payments->where('payment_status', 'partial')->sum('amount');
        $pendingRevenue = (float) $pendingPayments->sum('amount');

        // Method breakdown
        $methodCounts = [];
        foreach ($payments->groupBy('payment_method') as $method => $group) {
            $name = $method ? ucwords(str_replace('_', ' ', $method)) : 'Unspecified';
            $methodCounts[$name] = [
                'count' => $group->count(),
                'total' => (float) $group->sum('amount'),
            ];
        }

        $summary = [
            'total_payments_count' => $payments->count(),
            'total_revenue' => $totalRevenue,
            'pending_verification_amount' => $pendingRevenue,
            'paid_confirmed_count' => $verifiedPayments->count(),
            'pending_count' => $pendingPayments->count(),
            'rejected_count' => $rejectedPayments->count(),
            'methods_breakdown' => $methodCounts,
        ];

        $records = $payments->map(function (Payment $p) {
            return [
                'id' => $p->id,
                'booking_id' => $p->booking_id,
                'tourist_name' => $p->booking?->user?->name ?? 'Guest User',
                'package_name' => $p->booking?->tourDate?->package?->package_name ?? 'N/A',
                'tour_date' => $p->booking?->tourDate?->tour_date?->format('M d, Y') ?? 'N/A',
                'amount' => (float) $p->amount,
                'payment_method' => $p->payment_method ? ucwords(str_replace('_', ' ', $p->payment_method)) : 'N/A',
                'transaction_reference' => $p->transaction_reference ?? 'N/A',
                'payment_status' => $p->payment_status,
                'paid_at' => $p->paid_at ? $p->paid_at->format('M d, Y h:i A') : $p->created_at->format('M d, Y h:i A'),
                'notes' => $p->notes ?? '',
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
