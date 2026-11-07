<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Payment;
use App\Notifications\BookingConfirmedNotification;
use App\Notifications\PaymentConfirmedNotification;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class PayMongoCheckoutService
{
    public function isConfigured(): bool
    {
        return filled(config('payment.paymongo.secret_key'));
    }

    /** @return array{ok: bool, url?: string, message?: string} */
    public function createCheckout(
        Booking $booking,
        string $method,
        string $coverage = 'full'
    ): array {
        if ($method !== 'gcash' || $coverage !== 'full') {
            return [
                'ok' => false,
                'message' => 'Online reservations require the full booking amount by GCash.',
            ];
        }

        if (
            ! $this->isConfigured() ||
            $booking->booking_status !== 'pending'
        ) {
            return [
                'ok' => false,
                'message' => 'This booking is not available for online checkout.',
            ];
        }

        if (
            $booking->price_snapshot_source !== 'at_booking' ||
            $booking->totalCentavos() <= 0
        ) {
            return [
                'ok' => false,
                'message' => 'The original booking price must be verified before payment.',
            ];
        }

        $payments = Payment::query()
            ->where('booking_id', $booking->id)
            ->orderByDesc('id')
            ->get();

        if ($payments->contains(
            fn (Payment $p) => in_array($p->payment_status, ['paid', 'partial'], true)
        )) {
            return [
                'ok' => false,
                'message' => 'This booking already has a recorded payment.',
            ];
        }

        $pending = $payments->first(
            fn (Payment $p) => $p->payment_status === 'pending' &&
                $p->gateway === 'paymongo'
        );

        if ($pending !== null) {
            $session = $this->retrieveSession(
                (string) $pending->gateway_checkout_id
            );

            if ($session === null) {
                return [
                    'ok' => false,
                    'message' => 'Could not verify the previous checkout. No new charge was created. Try again later.',
                ];
            }

            $status = data_get($session, 'attributes.status');

            $expected = (int) round(
                (float) $pending->amount * 100
            );

            if (
                $this->matchingPaidPaymentId(
                    $session,
                    $expected
                ) !== null
            ) {
                $this->settleSession($booking, $session);

                return [
                    'ok' => false,
                    'message' => 'PayMongo received a payment. Refresh your booking before paying again.',
                ];
            }

            if ($status === 'expired') {
                DB::transaction(
                    function () use ($booking, $pending): void {
                        $locked = Booking::query()
                            ->whereKey($booking->id)
                            ->lockForUpdate()
                            ->firstOrFail();

                        $row = Payment::query()
                            ->whereKey($pending->id)
                            ->lockForUpdate()
                            ->firstOrFail();

                        if (
                            $locked->booking_status === 'pending' &&
                            $row->payment_status === 'pending'
                        ) {
                            $row->update([
                                'payment_status' => 'cancelled',
                                'notes' => 'Verified PayMongo checkout expired without payment.',
                            ]);
                        }
                    },
                    3
                );
            } else {
                if (
                    $status !== null &&
                    $status !== 'active'
                ) {
                    return [
                        'ok' => false,
                        'message' => 'The previous checkout has an unknown status. It needs review.',
                    ];
                }

                $url = data_get(
                    $session,
                    'attributes.checkout_url'
                );

                $pendingCents = (int) round(
                    (float) $pending->amount * 100
                );

                if ($pendingCents !== $this->chargeCentavos($booking, 'full')) {
                    return [
                        'ok' => false,
                        'message' => 'An older checkout for less than the full amount is still open. It was not replaced and no new charge was created.',
                    ];
                }

                if (
                    is_string($url) &&
                    str_starts_with(
                        $url,
                        'https://checkout.paymongo.com/'
                    )
                ) {
                    return [
                        'ok' => true,
                        'url' => $url,
                    ];
                }

                return [
                    'ok' => false,
                    'message' => 'The existing checkout could not be resumed. No replacement was created.',
                ];
            }
        }

        /*
         * A new checkout may replace only old sessions
         * explicitly verified as expired by PayMongo.
         */
        $unresolved = Payment::query()
            ->where('booking_id', $booking->id)
            ->get()
            ->contains(
                fn (Payment $p) => ! (
                    $p->gateway === 'paymongo' &&
                    $p->payment_status === 'cancelled' &&
                    str_starts_with(
                        (string) $p->notes,
                        'Verified PayMongo checkout expired'
                    )
                )
            );

        if ($unresolved) {
            return [
                'ok' => false,
                'message' => 'An earlier payment requires review before a new checkout.',
            ];
        }

        $booking->loadMissing([
            'tourDate.package',
            'user',
        ]);

        $centavos = $this->chargeCentavos(
            $booking,
            $coverage
        );

        $response = $this->request()->post(
            (string) config('payment.paymongo.checkout_url'),
            [
                'data' => [
                    'attributes' => [
                        'billing' => [
                            'name' => (string) (
                                $booking->user?->name ??
                                'HopSurigao guest'
                            ),
                            'email' => (string) (
                                $booking->user?->email ??
                                ''
                            ),
                            'phone' => (string)
                                $booking->phone_number,
                        ],
                        'line_items' => [[
                            'currency' => 'PHP',
                            'amount' => $centavos,
                            'name' => (string) (
                                $booking->tourDate?->package?->package_name
                                ?? 'Island tour'
                            ),
                            'quantity' => 1,
                            'description' => 'Booking #'
                                .$booking->id.' full GCash payment',
                        ]],
                        'payment_method_types' => ['gcash'],
                        'success_url' => $this->absoluteRoute(
                            'user.bookings.payment.return',
                            $booking
                        ),
                        'cancel_url' => $this->absoluteRoute(
                            'user.bookings.payment',
                            $booking
                        ),
                        'description' => 'HopSurigao booking #'
                            .$booking->id,
                        'reference_number' => $this->referenceFor(
                            $booking
                        ),
                        'metadata' => [
                            'booking_id' => (string) $booking->id,
                        ],
                        'send_email_receipt' => false,
                        'show_description' => true,
                        'show_line_items' => true,
                    ],
                ],
            ]
        );

        if (! $response->successful()) {
            return [
                'ok' => false,
                'message' => $this->failureMessage($response),
            ];
        }

        $sessionId = $response->json('data.id');
        $url = $response->json(
            'data.attributes.checkout_url'
        );

        if (
            ! is_string($sessionId) ||
            $sessionId === '' ||
            ! is_string($url) ||
            ! str_starts_with(
                $url,
                'https://checkout.paymongo.com/'
            )
        ) {
            return [
                'ok' => false,
                'message' => 'PayMongo returned an invalid checkout session. Contact support; do not retry payment yet.',
            ];
        }

        $saved = DB::transaction(
            function () use (
                $booking,
                $sessionId,
                $centavos
            ): bool {
                $locked = Booking::query()
                    ->whereKey($booking->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($locked->booking_status !== 'pending') {
                    return false;
                }

                $all = Payment::query()
                    ->where('booking_id', $locked->id)
                    ->get();

                if ($all->contains(
                    fn (Payment $p) => ! (
                        $p->gateway === 'paymongo' &&
                        $p->payment_status === 'cancelled' &&
                        str_starts_with(
                            (string) $p->notes,
                            'Verified PayMongo checkout expired'
                        )
                    )
                )) {
                    return false;
                }

                Payment::query()->create([
                    'booking_id' => $locked->id,
                    'amount' => $centavos / 100,
                    'payment_method' => 'gcash',
                    'gateway' => 'paymongo',
                    'gateway_checkout_id' => $sessionId,
                    'payment_status' => 'pending',
                    'transaction_reference' => null,
                    'notes' => 'Full GCash payment pending PayMongo confirmation. Tourist accepted the cancellation and refund policy.',
                    'paid_at' => null,
                ]);

                return true;
            },
            3
        );

        if (! $saved) {
            report(new \RuntimeException(
                'Unregistered PayMongo checkout '
                .$sessionId.' for booking '.$booking->id
            ));

            return [
                'ok' => false,
                'message' => 'Checkout could not be safely registered. Contact support before retrying.',
            ];
        }

        return [
            'ok' => true,
            'url' => $url,
        ];
    }

    /**
     * Expire a checkout before cancelling its booking.
     *
     * @return 'expired'|'paid'|'unresolved'
     */
    public function expireUnpaidSession(
        Booking $booking,
        string $checkoutId
    ): string {
        if (
            ! $this->isConfigured() ||
            $checkoutId === ''
        ) {
            return 'unresolved';
        }

        try {
            $this->request()->post(
                $this->sessionUrl($checkoutId).'/expire'
            );

            $session = $this->retrieveSession(
                $checkoutId
            );
        } catch (\Throwable $exception) {
            report($exception);

            return 'unresolved';
        }

        if ($session === null) {
            return 'unresolved';
        }

        $pending = Payment::query()
            ->where('booking_id', $booking->id)
            ->where(
                'gateway_checkout_id',
                $checkoutId
            )
            ->first();

        if ($pending === null) {
            return 'unresolved';
        }

        $expected = (int) round(
            (float) $pending->amount * 100
        );

        if (
            $this->matchingPaidPaymentId(
                $session,
                $expected
            ) !== null
        ) {
            $this->settleSession(
                $booking,
                $session
            );

            return 'paid';
        }

        return data_get(
            $session,
            'attributes.status'
        ) === 'expired'
            ? 'expired'
            : 'unresolved';
    }

    public function settleStoredCheckout(
        Booking $booking
    ): bool {
        $payment = Payment::query()
            ->where(
                'booking_id',
                $booking->id
            )
            ->where('gateway', 'paymongo')
            ->whereNotNull('gateway_checkout_id')
            ->latest('id')
            ->first();

        if ($payment === null) {
            return false;
        }

        if (in_array(
            $payment->payment_status,
            ['paid', 'partial'],
            true
        )) {
            return $booking->booking_status !== 'cancelled';
        }

        $session = $this->retrieveSession(
            (string) $payment->gateway_checkout_id
        );

        return $session !== null &&
            $this->settleSession(
                $booking,
                $session
            );
    }

    public function settleWebhook(
        string $payload
    ): void {
        $event = json_decode(
            $payload,
            true
        );

        if (
            ! is_array($event) ||
            $this->eventType($event)
                !== 'checkout_session.payment.paid'
        ) {
            return;
        }

        $session = $this->sessionFromEvent(
            $event
        );

        $booking = is_array($session)
            ? $this->bookingFromSession(
                $session
            )
            : null;

        if ($booking !== null) {
            $this->settleSession(
                $booking,
                $session
            );
        }
    }

    public function signatureIsValid(
        string $payload,
        ?string $header
    ): bool {
        $secret = (string) config(
            'payment.paymongo.webhook_secret'
        );

        if (
            $secret === '' ||
            ! is_string($header) ||
            $header === ''
        ) {
            return false;
        }

        $parts = [];

        foreach (
            explode(',', $header) as $piece
        ) {
            $pair = explode(
                '=',
                trim($piece),
                2
            );

            if (count($pair) === 2) {
                $parts[$pair[0]] = $pair[1];
            }
        }

        $timestamp = $parts['t'] ?? '';

        if (
            ! ctype_digit($timestamp) ||
            abs(
                time() - (int) $timestamp
            ) > 300
        ) {
            return false;
        }

        $event = json_decode(
            $payload,
            true
        );

        if (! is_array($event)) {
            return false;
        }

        $live = data_get(
            $event,
            'data.attributes.livemode',
            data_get($event, 'data.livemode')
        );

        $signature = $live === true
            ? ($parts['li'] ?? '')
            : ($parts['te'] ?? '');

        return is_string($signature) &&
            $signature !== '' &&
            hash_equals(
                hash_hmac(
                    'sha256',
                    $timestamp.'.'.$payload,
                    $secret
                ),
                $signature
            );
    }

    public function centavosFor(
        Booking $booking
    ): int {
        return $booking->totalCentavos();
    }

    public function chargeCentavos(
        Booking $booking,
        string $coverage
    ): int {
        if ($coverage !== 'full') {
            // Older callers may still pass a coverage label.
            // New online charges are always the saved full total.
        }

        return $this->centavosFor($booking);
    }

    private function settleSession(
        Booking $booking,
        array $session
    ): bool {
        $sessionId = $session['id'] ?? null;

        if (
            ! is_string($sessionId) ||
            $sessionId === '' ||
            data_get(
                $session,
                'attributes.reference_number'
            ) !== $this->referenceFor($booking)
        ) {
            return false;
        }

        $metadata = data_get(
            $session,
            'attributes.metadata.booking_id'
        );

        if (
            $metadata !== null &&
            (string) $metadata !== (string) $booking->id
        ) {
            return false;
        }

        $result = DB::transaction(
            function () use (
                $booking,
                $session,
                $sessionId
            ): array {
                $record = Booking::query()
                    ->whereKey($booking->id)
                    ->lockForUpdate()
                    ->first();

                if ($record === null) {
                    return [false, false, null];
                }

                $payment = Payment::query()
                    ->where(
                        'booking_id',
                        $record->id
                    )
                    ->where('gateway', 'paymongo')
                    ->where(
                        'gateway_checkout_id',
                        $sessionId
                    )
                    ->lockForUpdate()
                    ->first();

                $full = $this->centavosFor(
                    $record
                );

                $half = (int) round(
                    $full / 2
                );

                if ($full <= 0) {
                    return [false, false, null];
                }

                $expected = $payment !== null
                    ? (int) round(
                        (float) $payment->amount * 100
                    )
                    : null;

                if (
                    $expected !== null &&
                    ! in_array(
                        $expected,
                        [$full, $half],
                        true
                    )
                ) {
                    return [false, false, null];
                }

                $paidId = $expected !== null
                    ? $this->matchingPaidPaymentId(
                        $session,
                        $expected
                    )
                    : (
                        $this->matchingPaidPaymentId(
                            $session,
                            $full
                        )
                        ?? $this->matchingPaidPaymentId(
                            $session,
                            $half
                        )
                    );

                if ($paidId === null) {
                    return [false, false, null];
                }

                $grossCents = $expected
                    ?? (
                        $this->paidAmountCents(
                            $session,
                            $paidId
                        )
                        ?? 0
                    );

                $isCancelled = $record->booking_status
                    === 'cancelled';

                if (
                    $payment === null ||
                    strtolower(
                        (string) $payment->payment_method
                    ) !== 'gcash'
                ) {
                    $this->recordCase(
                        $record->id,
                        $payment?->id,
                        $sessionId,
                        $paidId,
                        $grossCents,
                        'unmatched_checkout'
                    );

                    return [false, false, null];
                }

                /*
                 * Payment received for a cancelled booking.
                 * Record the money and flag manual review.
                 * Never reopen the reservation automatically.
                 */
                if ($isCancelled) {
                    if (! in_array(
                        $payment->payment_status,
                        ['paid', 'partial'],
                        true
                    )) {
                        $payment->update([
                            'payment_status' => $grossCents < $full
                                    ? 'partial'
                                    : 'paid',
                            'transaction_reference' => $paidId,
                            'paid_at' => now(),
                            'notes' => 'Verified payment received after booking cancellation; manual reconciliation required.',
                        ]);
                    }

                    $this->recordCase(
                        $record->id,
                        $payment->id,
                        $sessionId,
                        $paidId,
                        $grossCents,
                        'paid_after_cancellation'
                    );

                    return [false, false, null];
                }

                if (! in_array(
                    $record->booking_status,
                    ['pending', 'confirmed'],
                    true
                )) {
                    $this->recordCase(
                        $record->id,
                        $payment->id,
                        $sessionId,
                        $paidId,
                        $grossCents,
                        'payment_unexpected_booking_state'
                    );

                    return [false, false, null];
                }

                if (in_array(
                    $payment->payment_status,
                    ['paid', 'partial'],
                    true
                )) {
                    if (
                        $payment->transaction_reference
                        !== $paidId
                    ) {
                        $this->recordCase(
                            $record->id,
                            $payment->id,
                            $sessionId,
                            $paidId,
                            $grossCents,
                            'payment_reference_mismatch'
                        );

                        return [false, false, null];
                    }

                    return [true, false, null];
                }

                if (
                    $payment->payment_status
                    !== 'pending'
                ) {
                    $this->recordCase(
                        $record->id,
                        $payment->id,
                        $sessionId,
                        $paidId,
                        $grossCents,
                        'payment_on_closed_checkout'
                    );

                    return [false, false, null];
                }

                $partial = $grossCents < $full;

                $payment->update([
                    'payment_status' => $partial ? 'partial' : 'paid',

                    'transaction_reference' => $paidId,

                    'notes' => $partial
                        ? '50% GCash downpayment confirmed by PayMongo.'
                        : 'Full GCash payment confirmed by PayMongo.',

                    'paid_at' => now(),
                ]);

                $newlyConfirmed =
                    $record->booking_status === 'pending';

                if ($newlyConfirmed) {
                    $record->update([
                        'booking_status' => 'confirmed',
                    ]);
                }

                return [
                    true,
                    $newlyConfirmed,
                    $payment->id,
                ];
            },
            3
        );

        [
            $success,
            $newlyConfirmed,
            $paymentId
        ] = $result;

        if (
            $success &&
            $paymentId !== null
        ) {
            try {
                $payment = Payment::query()->find(
                    $paymentId
                );

                if ($payment !== null) {
                    $booking->user?->notify(
                        new PaymentConfirmedNotification(
                            $payment
                        )
                    );
                }

                if ($newlyConfirmed) {
                    $booking->user?->notify(
                        new BookingConfirmedNotification(
                            $booking->fresh()
                        )
                    );
                }
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        return $success;
    }

    private function recordCase(
        int $bookingId,
        ?int $paymentId,
        string $checkoutId,
        string $paidId,
        int $amountCents,
        string $reason
    ): void {
        DB::table(
            'payment_reconciliation_cases'
        )->insertOrIgnore([
            'booking_id' => $bookingId,
            'payment_id' => $paymentId,
            'gateway_checkout_id' => $checkoutId,
            'gateway_payment_id' => $paidId,
            'amount' => $amountCents / 100,
            'reason' => $reason,
            'status' => 'needs_review',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function paidAmountCents(
        array $session,
        string $paidId
    ): ?int {
        foreach (
            (array) data_get(
                $session,
                'attributes.payments',
                []
            ) as $payment
        ) {
            if (
                ($payment['id'] ?? null) === $paidId &&
                is_numeric(
                    data_get(
                        $payment,
                        'attributes.amount'
                    )
                )
            ) {
                return (int) data_get(
                    $payment,
                    'attributes.amount'
                );
            }
        }

        return null;
    }

    private function matchingPaidPaymentId(
        array $session,
        int $expected
    ): ?string {
        foreach (
            (array) data_get(
                $session,
                'attributes.payments',
                []
            ) as $payment
        ) {
            $attributes = $payment['attributes']
                ?? null;

            if (
                is_array($attributes) &&
                ($attributes['status'] ?? null) === 'paid' &&
                ($attributes['currency'] ?? 'PHP') === 'PHP' &&
                is_numeric(
                    $attributes['amount'] ?? null
                ) &&
                (int) $attributes['amount'] === $expected &&
                is_string(
                    $payment['id'] ?? null
                ) &&
                $payment['id'] !== ''
            ) {
                return $payment['id'];
            }
        }

        return null;
    }

    private function retrieveSession(
        string $sessionId
    ): ?array {
        if ($sessionId === '') {
            return null;
        }

        try {
            $response = $this->request()->get(
                $this->sessionUrl($sessionId)
            );

            $session = $response->successful()
                ? $response->json('data')
                : null;

            return is_array($session) &&
                ($session['id'] ?? null) === $sessionId
                    ? $session
                    : null;
        } catch (\Throwable $exception) {
            report($exception);

            return null;
        }
    }

    private function sessionUrl(
        string $sessionId
    ): string {
        return rtrim(
            (string) config(
                'payment.paymongo.retrieve_url'
            ),
            '/'
        )
            .'/'.rawurlencode($sessionId);
    }

    private function eventType(
        array $event
    ): ?string {
        $type = data_get(
            $event,
            'data.attributes.type',
            data_get($event, 'data.type')
        );

        return is_string($type)
            ? $type
            : null;
    }

    private function sessionFromEvent(
        array $event
    ): ?array {
        $session = data_get(
            $event,
            'data.attributes.data',
            data_get($event, 'data.data')
        );

        return is_array($session)
            ? $session
            : null;
    }

    private function bookingFromSession(
        array $session
    ): ?Booking {
        $reference = data_get(
            $session,
            'attributes.reference_number'
        );

        if (
            ! is_string($reference) ||
            preg_match(
                '/^HS-(\d+)$/',
                $reference,
                $matches
            ) !== 1
        ) {
            return null;
        }

        $bookingId = (int) $matches[1];

        $metadata = data_get(
            $session,
            'attributes.metadata.booking_id'
        );

        if (
            $metadata !== null &&
            (string) $metadata !== (string) $bookingId
        ) {
            return null;
        }

        return Booking::query()->find(
            $bookingId
        );
    }

    private function referenceFor(
        Booking $booking
    ): string {
        return 'HS-'.$booking->id;
    }

    private function request(): PendingRequest
    {
        $pending = Http::withBasicAuth(
            (string) config(
                'payment.paymongo.secret_key'
            ),
            ''
        )
            ->acceptJson()
            ->asJson()
            ->timeout(20);

        $bundle = config(
            'payment.paymongo.ca_bundle'
        );

        if (
            is_string($bundle) &&
            is_file($bundle)
        ) {
            $pending = $pending->withOptions([
                'verify' => $bundle,
            ]);
        }

        return $pending;
    }

    private function failureMessage(
        Response $response
    ): string {
        if ($response->status() === 401) {
            return 'PayMongo rejected the secret key. Check PAYMONGO_SECRET_KEY.';
        }

        $detail = $response->json(
            'errors.0.detail'
        );

        return is_string($detail) &&
            $detail !== ''
                ? $detail
                : 'Unable to start PayMongo checkout. Try again later.';
    }

    private function absoluteRoute(
        string $name,
        Booking $booking
    ): string {
        return rtrim(
            request()->getSchemeAndHttpHost(),
            '/'
        )
            .'/'.ltrim(
                route($name, $booking, false),
                '/'
            );
    }
}
