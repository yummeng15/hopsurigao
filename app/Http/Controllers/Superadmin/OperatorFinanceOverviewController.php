<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\TourOperator;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/** Read-only platform oversight. Never changes a commission or payout. */
class OperatorFinanceOverviewController extends Controller
{
    public function __invoke(Request $request): Response
    {
        abort_unless($request->user()?->role?->name === 'Superadmin', 403);

        $data = $request->validate([
            'month' => ['nullable', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
        ]);

        $month = $data['month'] ?? now()->format('Y-m');
        $start = CarbonImmutable::createFromFormat('!Y-m', $month)->startOfMonth();
        $end = $start->addMonth();

        $bookingCounts = DB::table('bookings as b')
            ->join('tour_dates as td', 'td.id', '=', 'b.tour_date_id')
            ->join('packages as pk', 'pk.id', '=', 'td.package_id')
            ->whereNotNull('pk.tour_operator_id')
            ->where('b.created_at', '>=', $start->toDateTimeString())
            ->where('b.created_at', '<', $end->toDateTimeString())
            ->select('pk.tour_operator_id')
            ->selectRaw('COUNT(*) AS booking_count')
            ->selectRaw("SUM(CASE WHEN b.booking_status IN ('confirmed', 'completed') THEN 1 ELSE 0 END) AS confirmed_count")
            ->groupBy('pk.tour_operator_id')
            ->get()
            ->keyBy('tour_operator_id');

        $paymentCounts = DB::table('payments as p')
            ->join('bookings as b', 'b.id', '=', 'p.booking_id')
            ->join('tour_dates as td', 'td.id', '=', 'b.tour_date_id')
            ->join('packages as pk', 'pk.id', '=', 'td.package_id')
            ->whereNotNull('pk.tour_operator_id')
            ->whereIn('p.payment_status', ['paid', 'partial'])
            ->where('p.paid_at', '>=', $start->toDateTimeString())
            ->where('p.paid_at', '<', $end->toDateTimeString())
            ->select('pk.tour_operator_id')
            ->selectRaw('COUNT(*) AS payment_count')
            ->selectRaw('COALESCE(SUM(p.amount), 0) AS gross_received')
            ->selectRaw("COALESCE(SUM(CASE WHEN LOWER(p.payment_method) = 'cash' THEN p.amount ELSE 0 END), 0) AS cash_received")
            ->selectRaw("COALESCE(SUM(CASE WHEN LOWER(p.payment_method) = 'gcash' AND LOWER(COALESCE(p.gateway, '')) = 'paymongo' THEN p.amount ELSE 0 END), 0) AS gcash_received")
            ->groupBy('pk.tour_operator_id')
            ->get()
            ->keyBy('tour_operator_id');

        $operators = TourOperator::query()
            ->withCount(['users', 'packages'])
            ->orderBy('name')
            ->get(['id', 'name', 'is_active'])
            ->map(function (TourOperator $operator) use ($bookingCounts, $paymentCounts): array {
                $bookings = $bookingCounts->get($operator->id);
                $payments = $paymentCounts->get($operator->id);

                $gross = round((float) ($payments->gross_received ?? 0), 2);
                $cash = round((float) ($payments->cash_received ?? 0), 2);
                $gcash = round((float) ($payments->gcash_received ?? 0), 2);

                return [
                    'id' => (int) $operator->id,
                    'name' => (string) $operator->name,
                    'active' => (bool) $operator->is_active,
                    'admins' => (int) $operator->users_count,
                    'packages' => (int) $operator->packages_count,
                    'bookings' => (int) ($bookings->booking_count ?? 0),
                    'confirmed_bookings' => (int) ($bookings->confirmed_count ?? 0),
                    'payment_count' => (int) ($payments->payment_count ?? 0),
                    'gross_received' => $gross,
                    'cash_received' => $cash,
                    'gcash_received' => $gcash,
                    'other_received' => round(max(0, $gross - $cash - $gcash), 2),
                ];
            })
            ->values();

        // Older packages may still have no operator assignment. Flag their
        // payments separately instead of attributing them to the wrong business.
        $unassignedReceived = DB::table('payments as p')
            ->join('bookings as b', 'b.id', '=', 'p.booking_id')
            ->join('tour_dates as td', 'td.id', '=', 'b.tour_date_id')
            ->join('packages as pk', 'pk.id', '=', 'td.package_id')
            ->whereNull('pk.tour_operator_id')
            ->whereIn('p.payment_status', ['paid', 'partial'])
            ->where('p.paid_at', '>=', $start->toDateTimeString())
            ->where('p.paid_at', '<', $end->toDateTimeString())
            ->sum('p.amount');

        return Inertia::render('superadmin/OperatorFinance', [
            'month' => $start->format('Y-m'),
            'monthLabel' => $start->format('F Y'),
            'operators' => $operators->all(),
            'totals' => [
                'operators' => $operators->count(),
                'active_operators' => $operators->where('active', true)->count(),
                'bookings' => (int) $operators->sum('bookings'),
                'gross_received' => round((float) $operators->sum('gross_received'), 2),
                'cash_received' => round((float) $operators->sum('cash_received'), 2),
                'gcash_received' => round((float) $operators->sum('gcash_received'), 2),
                'other_received' => round((float) $operators->sum('other_received'), 2),
                'unassigned_received' => round((float) $unassignedReceived, 2),
            ],
        ]);
    }
}
