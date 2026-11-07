<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\RefundRequest;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RefundController extends Controller
{
    public function index(): Response
    {
        abort_unless(
            request()->user()?->role?->name === 'Superadmin',
            403
        );

        $requests = RefundRequest::withoutGlobalScopes()
            ->with([
                'user:id,name,email',
                'payment:id,amount,payment_status,payment_method',
                'booking.tourDate.package:id,package_name,destination,tour_operator_id',
                'tourOperator:id,name',
                'reviewer:id,name',
            ])
            ->latest('id')
            ->limit(100)
            ->get()
            ->map(fn (RefundRequest $refund): array => [
                'id' => $refund->id,
                'booking_id' => $refund->booking_id,
                'payment_id' => $refund->payment_id,
                'tourist' => $refund->user?->name ?? 'Tourist',
                'tourist_email' => $refund->user?->email,
                'operator' => $refund->tourOperator?->name ?? 'Operator',
                'package' => $refund->booking?->tourDate?->package?->package_name ?? 'Package',
                'amount' => (float) ($refund->payment?->amount ?? 0),
                'payment_status' => $refund->payment?->payment_status,
                'category' => $refund->category,
                'category_label' => $refund->categoryLabel(),
                'reason' => $refund->reason,
                'status' => $refund->status,
                'status_label' => $refund->statusLabel(),
                'rejection_reason' => $refund->rejection_reason,
                'reviewed_by' => $refund->reviewer?->name,
                'created_at' => $refund->created_at?->toDateTimeString(),
                'reviewed_at' => $refund->reviewed_at?->toDateTimeString(),
            ])
            ->all();

        return Inertia::render('admin/refunds/Index', [
            'refundRequests' => $requests,
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        abort_unless(
            $request->user()?->role?->name === 'Superadmin',
            403
        );

        $query = Payment::query()
            ->whereIn('payment_status', ['paid', 'refunded'])
            ->with([
                'booking.user',
                'booking.tourDate.package',
            ]);

        if ($search = $request->input('search')) {
            $query->where(function ($query) use ($search) {
                $query
                    ->where('id', 'like', "%{$search}%")
                    ->orWhere('booking_id', 'like', "%{$search}%")
                    ->orWhere('payment_method', 'like', "%{$search}%")
                    ->orWhere('payment_status', 'like', "%{$search}%")
                    ->orWhere('amount', 'like', "%{$search}%")
                    ->orWhereHas('booking.user', function ($query) use ($search) {
                        $query
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })
                    ->orWhereHas('booking.tourDate.package', function ($query) use ($search) {
                        $query->where('package_name', 'like', "%{$search}%");
                    });
            });
        }

        $allowedSorts = [
            'id',
            'booking_id',
            'amount',
            'payment_method',
            'payment_status',
            'paid_at',
            'created_at',
        ];

        $sort = $request->input('sort');

        if ($sort && in_array($sort, $allowedSorts, true)) {
            $order = $request->input('order', 'asc');

            if (! in_array($order, ['asc', 'desc'], true)) {
                $order = 'asc';
            }

            $query->orderBy($sort, $order);
        } else {
            $query->latest();
        }

        $perPage = min(max((int) $request->input('per_page', 10), 1), 100);

        return response()->json(
            $query->paginate($perPage)->through(function (Payment $payment) {
                return [
                    'id' => $payment->id,
                    'booking_id' => $payment->booking_id,
                    'user_name' => $payment->booking?->user?->name ?? 'Guest User',
                    'user_email' => $payment->booking?->user?->email,
                    'package_name' => $payment->booking?->tourDate?->package?->package_name ?? 'N/A',
                    'destination' => $payment->booking?->tourDate?->package?->destination,
                    'tour_date' => $payment->booking?->tourDate?->tour_date
                        ? Carbon::parse($payment->booking->tourDate->tour_date)->toDateString()
                        : null,
                    'amount' => (float) $payment->amount,
                    'payment_method' => $payment->payment_method,
                    'payment_status' => $payment->payment_status,
                    'paid_at' => $payment->paid_at?->format('Y-m-d H:i:s'),
                ];
            })
        );
    }

    public function store(Request $request, Payment $payment): RedirectResponse
    {
        abort(
            403,
            'Refunds are recorded by the Operator Admin. Payment oversight is view only.'
        );
    }
}
