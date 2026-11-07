<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class OperatorPaymentController extends Controller
{
    public function index(Request $request): Response
    {
        $operatorId = $this->operatorId($request);

        $filters = $request->validate([
            'status' => [
                'nullable',
                Rule::in([
                    'pending',
                    'paid',
                    'refunded',
                ]),
            ],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $query = Payment::query()
            ->whereHas(
                'booking.tourDate.package',
                fn (Builder $packages) => $packages->where('tour_operator_id', $operatorId)
            )
            ->with([
                'booking.user:id,name',
                'booking.tourDate.package:id,package_name',
            ]);

        if (! empty($filters['status'])) {
            $query->where(
                'payment_status',
                $filters['status']
            );
        }

        $search = trim((string) ($filters['search'] ?? ''));

        if ($search !== '') {
            $query->where(
                function (Builder $payments) use ($search): void {
                    if (ctype_digit($search)) {
                        $payments->orWhere(
                            'booking_id',
                            (int) $search
                        );
                    }

                    $payments->orWhereHas(
                        'booking.user',
                        fn (Builder $users) => $users->where(
                            'name',
                            'like',
                            '%'.$search.'%'
                        )
                    );

                    $payments->orWhereHas(
                        'booking.tourDate.package',
                        fn (Builder $packages) => $packages->where(
                            'package_name',
                            'like',
                            '%'.$search.'%'
                        )
                    );
                }
            );
        }

        $payments = $query
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Payment $payment): array => [
                'id' => (int) $payment->id,
                'booking_id' => (int) $payment->booking_id,
                'tourist' => (string) (
                    $payment->booking?->user?->name
                    ?? 'Unknown tourist'
                ),
                'package' => (string) (
                    $payment->booking?->tourDate?->package?->package_name
                    ?? 'Unknown package'
                ),
                'amount' => (float) $payment->amount,
                'method' => (string) $payment->payment_method,
                'status' => (string) $payment->payment_status,
                'paid_at' => $payment->paid_at?->format('Y-m-d H:i'),
            ]);

        return Inertia::render('operator/Payments', [
            'payments' => $payments,
            'filters' => [
                'search' => $search,
                'status' => (string) ($filters['status'] ?? ''),
            ],
        ]);
    }

    public function show(
        Request $request,
        int $booking
    ): Response {
        $operatorId = $this->operatorId($request);

        $record = $this->ownedBooking($operatorId, $booking)
            ->with([
                'user:id,name',
                'tourDate.package:id,package_name,price',
                'payments',
            ])
            ->firstOrFail();

        $received = $record->amountReceived();
        $remaining = $record->remainingBalance();

        /*
         * A reported no-show must not be treated
         * as a normal outstanding cash balance.
         */
        $unresolvedTripReport = DB::table('booking_trip_records')
            ->where('booking_id', $record->id)
            ->whereIn('outcome', [
                'passenger_no_show',
                'operator_no_show',
            ])
            ->exists();

        $hasVerifiedDownpayment = $record->payments
            ->contains(fn (Payment $payment): bool => strtolower((string) $payment->gateway) === 'paymongo'
                && strtolower((string) $payment->payment_method) === 'gcash'
                && $payment->payment_status === 'partial'
                && $payment->paid_at !== null
            );

        return Inertia::render('operator/PaymentDetails', [
            'booking' => [
                'id' => (int) $record->id,
                'tourist' => (string) (
                    $record->user?->name ?? 'Unknown tourist'
                ),
                'package' => (string) (
                    $record->tourDate?->package?->package_name
                    ?? 'Unknown package'
                ),
                'status' => (string) $record->booking_status,
                'total' => $record->packageTotal(),
                'received' => $received,
                'remaining' => $remaining,

                'canCollect' => in_array(
                    $record->booking_status,
                    ['confirmed', 'completed'],
                    true
                )
                    && $hasVerifiedDownpayment
                    && $remaining > 0
                    && ! $unresolvedTripReport,

                'payments' => $record->payments
                    ->sortByDesc('id')
                    ->map(fn (Payment $payment): array => [
                        'id' => (int) $payment->id,
                        'amount' => (float) $payment->amount,
                        'method' => (string) $payment->payment_method,
                        'status' => (string) $payment->payment_status,
                        'paid_at' => $payment->paid_at?->format('Y-m-d H:i'),
                        'reference' => $payment->transaction_reference,
                    ])
                    ->values()
                    ->all(),
            ],
            'collectionKey' => (string) Str::uuid(),
        ]);
    }

    public function collect(
        Request $request,
        int $booking
    ): RedirectResponse {
        $operatorId = $this->operatorId($request);

        $data = $request->validate([
            'amount' => [
                'required',
                'numeric',
                'decimal:0,2',
                'min:0.01',
                'max:99999999.99',
            ],
            'collection_key' => ['required', 'uuid'],
            'cash_received' => ['accepted'],
        ]);

        $reference = 'operator-balance:'.$data['collection_key'];

        $created = DB::transaction(
            function () use (
                $operatorId,
                $booking,
                $data,
                $reference
            ): bool {
                $record = $this->ownedBooking($operatorId, $booking)
                    ->with('tourDate.package')
                    ->lockForUpdate()
                    ->firstOrFail();

                /*
                 * NEW: Block cash collection for no-show reports.
                 * Check under the booking lock to protect against
                 * simultaneous trip outcome and payment submissions.
                 */
                if (
                    DB::table('booking_trip_records')
                        ->where('booking_id', $record->id)
                        ->whereIn('outcome', [
                            'passenger_no_show',
                            'operator_no_show',
                        ])
                        ->exists()
                ) {
                    throw ValidationException::withMessages([
                        'amount' => 'This booking has a no-show report under review. Cash collection is blocked.',
                    ]);
                }

                // Prevent duplicate cash submissions.
                if (
                    DB::table('payments')
                        ->where('booking_id', $record->id)
                        ->where(
                            'transaction_reference',
                            $reference
                        )
                        ->exists()
                ) {
                    return false;
                }

                if (! in_array(
                    $record->booking_status,
                    ['confirmed', 'completed'],
                    true
                )) {
                    throw ValidationException::withMessages([
                        'amount' => 'Cash balances can only be collected for confirmed or completed bookings.',
                    ]);
                }

                // The online downpayment must be verified first.
                $hasVerifiedDownpayment = DB::table('payments')
                    ->where('booking_id', $record->id)
                    ->where('gateway', 'paymongo')
                    ->where('payment_method', 'gcash')
                    ->where('payment_status', 'partial')
                    ->whereNotNull('paid_at')
                    ->exists();

                if (! $hasVerifiedDownpayment) {
                    throw ValidationException::withMessages([
                        'amount' => 'A verified PayMongo GCash downpayment is required first.',
                    ]);
                }

                /*
                 * Calculate using the saved booking price.
                 * Never use the current package catalog price.
                 */
                $totalCents = $record->totalCentavos();

                $receivedCents = (int) round(
                    (float) DB::table('payments')
                        ->where('booking_id', $record->id)
                        ->whereIn(
                            'payment_status',
                            ['paid', 'partial']
                        )
                        ->sum('amount') * 100
                );

                $remainingCents = max(
                    0,
                    $totalCents - $receivedCents
                );

                $amountCents = (int) round(
                    (float) $data['amount'] * 100
                );

                if ($remainingCents <= 0) {
                    throw ValidationException::withMessages([
                        'amount' => 'This booking is already fully paid.',
                    ]);
                }

                if ($amountCents > $remainingCents) {
                    throw ValidationException::withMessages([
                        'amount' => 'Cash received cannot exceed the remaining balance.',
                    ]);
                }

                Payment::query()->create([
                    'booking_id' => $record->id,
                    'amount' => $amountCents / 100,
                    'payment_method' => 'cash',
                    'payment_status' => 'paid',
                    'transaction_reference' => $reference,
                    'notes' => 'Operator collected remaining balance in cash.',
                    'paid_at' => now(),
                ]);

                /*
                 * Keep the original GCash downpayment.
                 * The new cash payment becomes a separate record.
                 */
                return true;
            },
            3
        );

        return redirect()
            ->route('operator.payments.show', $booking)
            ->with(
                'success',
                $created
                    ? 'Cash payment recorded.'
                    : 'This cash submission was already recorded.'
            );
    }

    public function refund(Request $request, Payment $payment): RedirectResponse
    {
        $this->operatorId($request);

        /*
         * Direct Mark refunded is closed. Tourist-eligible requests
         * must be reviewed in Refund Management.
         */
        abort(
            403,
            'Use Refund Management to approve or reject tourist refund requests. Direct refunds are not allowed.'
        );
    }

    private function operatorId(Request $request): int
    {
        $user = $request->user();

        abort_unless(
            $user?->role?->name === 'Admin',
            403
        );

        abort_unless(
            $user->tourOperator?->is_active === true,
            403
        );

        return (int) $user->tour_operator_id;
    }

    /** @return Builder<Booking> */
    private function ownedBooking(
        int $operatorId,
        int $bookingId
    ): Builder {
        return Booking::query()
            ->whereKey($bookingId)
            ->whereHas(
                'tourDate.package',
                fn (Builder $packages) => $packages->where(
                    'tour_operator_id',
                    $operatorId
                )
            );
    }
}
