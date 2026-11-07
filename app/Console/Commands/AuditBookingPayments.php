<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only booking/payment integrity audit.
 *
 * This command never changes booking records,
 * payment statuses, amounts, or PayMongo transactions.
 */
class AuditBookingPayments extends Command
{
    protected $signature = 'hopsurigao:audit-payments
        {--booking= : Optional booking ID to inspect}
        {--json : Output findings as JSON}
        {--strict : Exit with code 1 when issues are found}';

    protected $description =
        'Audit saved booking prices, payments, and reconciliation cases';

    public function handle(): int
    {
        foreach (['bookings', 'payments'] as $table) {
            if (!Schema::hasTable($table)) {
                $this->error(
                    "Missing {$table} table. Apply your migrations first."
                );

                return self::FAILURE;
            }
        }

        foreach (
            [
                'booked_total',
                'booked_unit_price',
                'price_snapshot_source',
            ] as $field
        ) {
            if (!Schema::hasColumn('bookings', $field)) {
                $this->error(
                    "Missing bookings.{$field}. Apply Fix 4A first."
                );

                return self::FAILURE;
            }
        }

        $bookingOption = $this->option('booking');

        if (
            $bookingOption !== null &&
            (
                !is_string($bookingOption) ||
                !ctype_digit($bookingOption) ||
                (int) $bookingOption < 1
            )
        ) {
            $this->error(
                'The booking ID must be a positive integer.'
            );

            return self::FAILURE;
        }

        $selectedId = $bookingOption !== null
            ? (int) $bookingOption
            : null;

        $issues = [];
        $scanned = 0;

        /*
        |--------------------------------------------------------------------------
        | LOAD BOOKING RECORDS
        |--------------------------------------------------------------------------
        */

        $query = DB::table('bookings')
            ->select([
                'id',
                'booking_status',
                'number_of_guests',
                'booked_unit_price',
                'booked_total',
                'price_snapshot_source',
            ]);

        if ($selectedId !== null) {
            $query->where('id', $selectedId);
        }

        /*
        |--------------------------------------------------------------------------
        | AUDIT BOOKING PRICES AND PAYMENTS
        |--------------------------------------------------------------------------
        */

        $query->orderBy('id')
            ->chunkById(
                200,
                function ($bookings) use (&$issues, &$scanned): void {
                    $ids = $bookings->pluck('id')->all();

                    $paymentGroups = DB::table('payments')
                        ->whereIn('booking_id', $ids)
                        ->select([
                            'id',
                            'booking_id',
                            'amount',
                            'gateway',
                            'gateway_checkout_id',
                            'payment_method',
                            'payment_status',
                            'transaction_reference',
                            'paid_at',
                        ])
                        ->get()
                        ->groupBy('booking_id');

                    foreach ($bookings as $booking) {
                        $scanned++;

                        $id = (int) $booking->id;

                        $total = $this->cents(
                            $booking->booked_total
                        );

                        $unit = $this->cents(
                            $booking->booked_unit_price
                        );

                        $payments = $paymentGroups->get(
                            $id,
                            collect()
                        );

                        $received = 0;
                        $pendingCheckouts = 0;

                        /*
                         * Check saved prices.
                         */

                        if (
                            $total === null ||
                            $total <= 0
                        ) {
                            $this->issue(
                                $issues,
                                $id,
                                'missing_saved_price',
                                'Saved booking total is missing or invalid.'
                            );
                        }

                        if (
                            $booking->price_snapshot_source
                            !== 'at_booking'
                        ) {
                            $this->issue(
                                $issues,
                                $id,
                                'unverified_price',
                                'Original booking price requires verification.'
                            );
                        }

                        if (
                            $total !== null &&
                            $total > 0 &&
                            $unit !== null &&
                            $unit > 0
                        ) {
                            $expected = $unit *
                                (int) $booking->number_of_guests;

                            if (abs($total - $expected) > 1) {
                                $this->issue(
                                    $issues,
                                    $id,
                                    'price_mismatch',
                                    'Saved unit price multiplied by guests differs from saved total.'
                                );
                            }
                        }

                        /*
                         * Check payment records.
                         */

                        foreach ($payments as $payment) {
                            $amount = $this->cents(
                                $payment->amount
                            );

                            $status = strtolower(
                                (string) $payment->payment_status
                            );

                            $gateway = strtolower(
                                (string) $payment->gateway
                            );

                            if (
                                $amount === null ||
                                $amount <= 0
                            ) {
                                $this->issue(
                                    $issues,
                                    $id,
                                    'invalid_payment_amount',
                                    'Payment #'.$payment->id.' has an invalid amount.'
                                );
                            }

                            if (
                                in_array(
                                    $status,
                                    ['paid', 'partial'],
                                    true
                                ) &&
                                $amount !== null &&
                                $amount > 0
                            ) {
                                $received += $amount;

                                if (
                                    $gateway === 'paymongo' &&
                                    (
                                        empty($payment->gateway_checkout_id) ||
                                        empty($payment->transaction_reference) ||
                                        $payment->paid_at === null
                                    )
                                ) {
                                    $this->issue(
                                        $issues,
                                        $id,
                                        'incomplete_gateway_receipt',
                                        'PayMongo payment #'.$payment->id.' has incomplete verification details.'
                                    );
                                }
                            }

                            if (
                                $gateway === 'paymongo' &&
                                $status === 'pending'
                            ) {
                                $pendingCheckouts++;

                                if (
                                    empty(
                                        $payment->gateway_checkout_id
                                    )
                                ) {
                                    $this->issue(
                                        $issues,
                                        $id,
                                        'missing_checkout_id',
                                        'Pending PayMongo payment #'.$payment->id.' has no checkout ID.'
                                    );
                                }
                            }
                        }

                        /*
                         * Check duplicate pending checkouts.
                         */

                        if ($pendingCheckouts > 1) {
                            $this->issue(
                                $issues,
                                $id,
                                'multiple_pending_checkouts',
                                'Multiple PayMongo checkouts are pending.'
                            );
                        }

                        /*
                         * Check overpayments.
                         */

                        if (
                            $total !== null &&
                            $total > 0 &&
                            $received > $total + 1
                        ) {
                            $this->issue(
                                $issues,
                                $id,
                                'overpaid',
                                'Recorded payments exceed the saved booking total.'
                            );
                        }

                        /*
                         * Check booking/payment status conflicts.
                         */

                        $bookingStatus = strtolower(
                            (string) $booking->booking_status
                        );

                        if (
                            $bookingStatus === 'pending' &&
                            $received > 0
                        ) {
                            $this->issue(
                                $issues,
                                $id,
                                'pending_with_payment',
                                'Pending booking has a recorded payment.'
                            );
                        }

                        if (
                            $bookingStatus === 'cancelled' &&
                            $received > 0
                        ) {
                            $this->issue(
                                $issues,
                                $id,
                                'cancelled_with_payment',
                                'Cancelled booking received money; reconciliation may be required.'
                            );
                        }

                        if (
                            in_array(
                                $bookingStatus,
                                ['confirmed', 'completed'],
                                true
                            ) &&
                            $received === 0
                        ) {
                            $this->issue(
                                $issues,
                                $id,
                                'confirmed_without_payment',
                                'Confirmed or completed booking has no recorded payment.'
                            );
                        }
                    }
                }
            );

        /*
        |--------------------------------------------------------------------------
        | UNRESOLVED RECONCILIATION CASES
        |--------------------------------------------------------------------------
        */

        if (
            Schema::hasTable(
                'payment_reconciliation_cases'
            )
        ) {
            $cases = DB::table(
                'payment_reconciliation_cases'
            )->where(
                'status',
                'needs_review'
            );

            if ($selectedId !== null) {
                $cases->where(
                    'booking_id',
                    $selectedId
                );
            }

            $cases
                ->select([
                    'id',
                    'booking_id',
                    'reason',
                ])
                ->orderBy('id')
                ->chunkById(
                    200,
                    function ($rows) use (&$issues): void {
                        foreach ($rows as $case) {
                            $this->issue(
                                $issues,
                                (int) $case->booking_id,
                                'reconciliation_pending',
                                'Case #'.$case->id.' ('.$case->reason.') needs manual review.'
                            );
                        }
                    }
                );
        }

        /*
        |--------------------------------------------------------------------------
        | DUPLICATE PAYMONGO REFERENCES
        |--------------------------------------------------------------------------
        */

        $duplicates = DB::table('payments')
            ->where('gateway', 'paymongo')
            ->whereIn(
                'payment_status',
                ['paid', 'partial']
            )
            ->whereNotNull(
                'transaction_reference'
            )
            ->where(
                'transaction_reference',
                '<>',
                ''
            );

        if ($selectedId !== null) {
            $references = DB::table('payments')
                ->where('booking_id', $selectedId)
                ->whereNotNull(
                    'transaction_reference'
                )
                ->pluck(
                    'transaction_reference'
                );

            $duplicates->whereIn(
                'transaction_reference',
                $references->all()
            );
        }

        $duplicates
            ->select('transaction_reference')
            ->selectRaw(
                'COUNT(*) AS ledger_count'
            )
            ->groupBy('transaction_reference')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->each(
                function ($row) use (
                    &$issues,
                    $selectedId
                ): void {
                    $bookings = DB::table('payments')
                        ->where('gateway', 'paymongo')
                        ->where(
                            'transaction_reference',
                            $row->transaction_reference
                        )
                        ->pluck('booking_id')
                        ->unique();

                    foreach ($bookings as $bookingId) {
                        if (
                            $selectedId === null ||
                            (int) $bookingId === $selectedId
                        ) {
                            $this->issue(
                                $issues,
                                (int) $bookingId,
                                'duplicate_gateway_reference',
                                'The same PayMongo payment reference is used more than once.'
                            );
                        }
                    }
                }
            );

        /*
        |--------------------------------------------------------------------------
        | RESULTS
        |--------------------------------------------------------------------------
        */

        if (
            $selectedId !== null &&
            $scanned === 0
        ) {
            $this->error(
                'Booking #'.$selectedId.' was not found.'
            );

            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line(
                json_encode(
                    [
                        'bookings_scanned' => $scanned,
                        'issue_count' => count($issues),
                        'issues' => $issues,
                    ],
                    JSON_UNESCAPED_SLASHES |
                    JSON_THROW_ON_ERROR
                )
            );
        } else {
            $this->info(
                "Audited {$scanned} booking(s). Found ".
                count($issues).
                ' issue(s).'
            );

            if ($issues !== []) {
                $this->table(
                    [
                        'Booking',
                        'Code',
                        'What needs review',
                    ],
                    array_map(
                        fn (array $issue): array => [
                            $issue['booking_id'],
                            $issue['code'],
                            $issue['message'],
                        ],
                        $issues
                    )
                );

                $this->warn(
                    'Read-only audit: no booking or payment records were changed.'
                );
            }
        }

        return $this->option('strict') &&
            $issues !== []
                ? self::FAILURE
                : self::SUCCESS;
    }

    private function cents(
        mixed $value
    ): ?int {
        if (
            $value === null ||
            !is_numeric($value)
        ) {
            return null;
        }

        $number = (float) $value;

        return is_finite($number)
            ? (int) round($number * 100)
            : null;
    }

    /**
     * @param array<int, array{
     *     booking_id: int,
     *     code: string,
     *     message: string
     * }> $issues
     */
    private function issue(
        array &$issues,
        int $bookingId,
        string $code,
        string $message
    ): void {
        $issues[] = [
            'booking_id' => $bookingId,
            'code' => $code,
            'message' => $message,
        ];
    }
}
