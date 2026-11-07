<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\RefundRequest;
use App\Notifications\RefundDecisionNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class OperatorRefundController extends Controller
{
    public function index(Request $request): Response
    {
        $operatorId = $this->operatorId($request);

        $status = (string) $request->string('status');
        $search = trim((string) $request->string('search'));

        $query = RefundRequest::query()
            ->where('tour_operator_id', $operatorId)
            ->with([
                'user:id,name,email',
                'payment:id,amount,payment_status,payment_method',
                'booking.tourDate.package:id,package_name,destination,tour_operator_id',
            ])
            ->latest('id');

        if (in_array($status, [
            RefundRequest::STATUS_PENDING,
            RefundRequest::STATUS_APPROVED,
            RefundRequest::STATUS_REJECTED,
            RefundRequest::STATUS_REFUNDED_TEST,
        ], true)) {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $builder
                    ->where('booking_id', 'like', "%{$search}%")
                    ->orWhere('id', 'like', "%{$search}%")
                    ->orWhereHas('user', function (Builder $users) use ($search): void {
                        $users
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })
                    ->orWhereHas('booking.tourDate.package', function (Builder $packages) use ($search): void {
                        $packages->where('package_name', 'like', "%{$search}%");
                    });
            });
        }

        $refunds = $query
            ->paginate(15)
            ->withQueryString()
            ->through(fn (RefundRequest $refund): array => $this->row($refund));

        return Inertia::render('operator/Refunds', [
            'refunds' => $refunds,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
        ]);
    }

    public function show(Request $request, RefundRequest $refund): Response
    {
        $this->assertOwned($request, $refund);

        $refund->load([
            'user:id,name,email',
            'payment',
            'booking.tourDate.package',
            'reviewer:id,name',
        ]);

        return Inertia::render('operator/RefundDetails', [
            'refund' => $this->row($refund, detailed: true),
        ]);
    }

    public function approve(Request $request, RefundRequest $refund): RedirectResponse
    {
        $this->assertOwned($request, $refund);

        DB::transaction(function () use ($request, $refund): void {
            $record = RefundRequest::query()
                ->whereKey($refund->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $record->isPending()) {
                throw ValidationException::withMessages([
                    'refund' => 'Only a pending refund request can be approved.',
                ]);
            }

            $payment = Payment::query()
                ->whereKey($record->payment_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($payment->payment_status !== 'paid') {
                throw ValidationException::withMessages([
                    'refund' => 'Only a paid payment can be refunded. Unpaid or already refunded payments are not eligible.',
                ]);
            }

            $payment->update([
                'payment_status' => 'refunded',
                'notes' => trim(
                    ($payment->notes ? $payment->notes."\n" : '')
                    .'Simulated refund (PayMongo Test Mode). No real money was returned.'
                ),
            ]);

            $record->update([
                'status' => RefundRequest::STATUS_REFUNDED_TEST,
                'rejection_reason' => null,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'processed_at' => now(),
            ]);

            $record->user?->notify(new RefundDecisionNotification($record->fresh()));
        });

        return back()->with('success', 'Refund approved and recorded as Refunded (Test). No real money was transferred.');
    }

    public function reject(Request $request, RefundRequest $refund): RedirectResponse
    {
        $this->assertOwned($request, $refund);

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        DB::transaction(function () use ($request, $refund, $validated): void {
            $record = RefundRequest::query()
                ->whereKey($refund->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $record->isPending()) {
                throw ValidationException::withMessages([
                    'rejection_reason' => 'Only a pending refund request can be rejected.',
                ]);
            }

            $record->update([
                'status' => RefundRequest::STATUS_REJECTED,
                'rejection_reason' => $validated['rejection_reason'],
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ]);

            $record->user?->notify(new RefundDecisionNotification($record->fresh()));
        });

        return back()->with('success', 'Refund request rejected.');
    }

    /**
     * @return array<string, mixed>
     */
    private function row(RefundRequest $refund, bool $detailed = false): array
    {
        $payload = [
            'id' => $refund->id,
            'booking_id' => $refund->booking_id,
            'payment_id' => $refund->payment_id,
            'tourist' => $refund->user?->name ?? 'Tourist',
            'tourist_email' => $refund->user?->email,
            'package' => $refund->booking?->tourDate?->package?->package_name ?? 'Package',
            'destination' => $refund->booking?->tourDate?->package?->destination,
            'amount' => (float) ($refund->payment?->amount ?? 0),
            'payment_status' => $refund->payment?->payment_status,
            'payment_method' => $refund->payment?->payment_method,
            'category' => $refund->category,
            'category_label' => $refund->categoryLabel(),
            'reason' => $refund->reason,
            'status' => $refund->status,
            'status_label' => $refund->statusLabel(),
            'rejection_reason' => $refund->rejection_reason,
            'created_at' => $refund->created_at?->toDateTimeString(),
            'reviewed_at' => $refund->reviewed_at?->toDateTimeString(),
            'processed_at' => $refund->processed_at?->toDateTimeString(),
        ];

        if ($detailed) {
            $payload['reviewed_by'] = $refund->reviewer?->name;
        }

        return $payload;
    }

    private function assertOwned(Request $request, RefundRequest $refund): void
    {
        $operatorId = $this->operatorId($request);

        abort_unless(
            (int) $refund->tour_operator_id === $operatorId,
            403,
            'You can only manage refunds for your own bookings.'
        );
    }

    private function operatorId(Request $request): int
    {
        $user = $request->user();

        abort_unless($user?->role?->name === 'Admin', 403);

        abort_unless(
            $user->tourOperator?->is_active === true,
            403,
            'You must belong to an active tour operator.'
        );

        return (int) $user->tour_operator_id;
    }
}
