
<?php

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use App\Services\PayMongoCheckoutService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    $this->withoutVite();

    Http::preventStrayRequests();

    config([
        'payment.paymongo.secret_key' => 'sk_test_example',
        'payment.paymongo.webhook_secret' => 'whsec_test',
    ]);
});

test('tourist checkout sends the full amount and settles only a matching payment', function () {
    [$tourist, $booking] = securePaymongoBooking();

    $amount = app(
        PayMongoCheckoutService::class
    )->centavosFor($booking);

    secureFakeCheckout($booking, $amount);

    $this->actingAs($tourist)
        ->post(
            route('user.bookings.payment.checkout', $booking),
            [
                'payment_method' => 'gcash',
                'payment_coverage' => 'full',
                'accepted_policy' => true,
            ]
        )
        ->assertRedirect(
            'https://checkout.paymongo.com/cs_test_123'
        );

    Http::assertSent(
        fn ($request) => $request->method() === 'POST'
            && (
                $request['data']['attributes']['payment_method_types']
                ?? null
            ) === ['gcash']
            && (
                $request['data']['attributes']['line_items'][0]['amount']
                ?? null
            ) === $amount
    );

    $record = Payment::query()
        ->where('booking_id', $booking->id)
        ->firstOrFail();

    expect($record->payment_status)->toBe('pending')
        ->and($record->gateway_checkout_id)->toBe(
            'cs_test_123'
        );

    $this->get(
        route('user.bookings.payment.return', $booking)
    )->assertRedirect(
        route('user.bookings.feedback.create', $booking)
    );

    expect($booking->fresh()->booking_status)->toBe(
        'confirmed'
    )
        ->and($booking->fresh()->qr_token)->not->toBeNull()
        ->and((int) round((float) $record->fresh()->amount * 100))->toBe($amount)
        ->and($record->fresh()->payment_status)->toBe(
            'paid'
        )
        ->and($record->fresh()->transaction_reference)->toBe(
            'pay_test_123'
        );
});

test('a PayMongo response with the wrong gross amount cannot settle a booking', function () {
    [$tourist, $booking] = securePaymongoBooking();

    $amount = app(
        PayMongoCheckoutService::class
    )->centavosFor($booking);

    securePendingPayment($booking, $amount);

    /*
     * The net amount matches,
     * but the charged gross amount is wrong.
     */
    Http::fake([
        'https://api.paymongo.com/v1/checkout_sessions/*' => Http::response([
            'data' => securePaymongoSession(
                $booking,
                $amount + 500,
                $amount
            ),
        ]),
    ]);

    $this->actingAs($tourist)
        ->get(
            route('user.bookings.payment.return', $booking)
        )
        ->assertRedirect(
            route('user.bookings.payment', $booking)
        );

    expect($booking->fresh()->booking_status)->toBe(
        'pending'
    )
        ->and(
            Payment::query()
                ->where('booking_id', $booking->id)
                ->first()
                ->payment_status
        )->toBe('pending');
});

test('a valid signed webhook confirms a booking once and invalid signatures are denied', function () {
    [$tourist, $booking] = securePaymongoBooking();

    $amount = app(
        PayMongoCheckoutService::class
    )->centavosFor($booking);

    $paymentId = securePendingPayment(
        $booking,
        $amount
    );

    $payload = securePaymongoWebhook(
        $booking,
        $amount,
        false
    );

    securePostWebhook(
        $this,
        $payload,
        't='.time().',te=wrong,li='
    )->assertForbidden();

    expect($booking->fresh()->booking_status)->toBe(
        'pending'
    );

    $header = secureWebhookHeader(
        $payload,
        false
    );

    securePostWebhook(
        $this,
        $payload,
        $header
    )->assertOk();

    securePostWebhook(
        $this,
        $payload,
        $header
    )->assertOk();

    expect($booking->fresh()->booking_status)->toBe(
        'confirmed'
    )
        ->and(
            Payment::query()
                ->findOrFail($paymentId)
                ->payment_status
        )->toBe('paid')
        ->and(
            Payment::query()
                ->where('booking_id', $booking->id)
                ->count()
        )->toBe(1);

    expect($tourist)->not->toBeNull();
});

test('a payment receipt image cannot bypass hosted PayMongo checkout', function () {
    [$tourist, $booking] = securePaymongoBooking();

    $this->actingAs($tourist)
        ->post(
            route('user.bookings.payment.submit', $booking),
            [
                'payment_method' => 'gcash',
                'proof_of_payment' => UploadedFile::fake()->image('receipt.jpg'),
            ]
        )
        ->assertSessionHasErrors('payment_method');

    expect(
        Payment::query()
            ->where('booking_id', $booking->id)
            ->exists()
    )->toBeFalse();
});

test('checkout requires a PayMongo secret key', function () {
    config([
        'payment.paymongo.secret_key' => null,
    ]);

    [$tourist, $booking] = securePaymongoBooking();

    $this->actingAs($tourist)
        ->post(
            route('user.bookings.payment.checkout', $booking),
            [
                'payment_method' => 'gcash',
                'payment_coverage' => 'full',
                'accepted_policy' => true,
            ]
        )
        ->assertSessionHasErrors('payment_method');
});

test('tourists cannot choose Maya or bank transfer for online checkout', function () {
    [$tourist, $booking] = securePaymongoBooking();

    $this->actingAs($tourist);

    foreach (['maya', 'bank_transfer'] as $method) {
        $this->post(
            route('user.bookings.payment.checkout', $booking),
            [
                'payment_method' => $method,
            ]
        )
            ->assertSessionHasErrors('payment_method');
    }

    expect(
        file_get_contents(
            resource_path(
                'js/pages/user/bookings/Payment.vue'
            )
        )
    )
        ->not->toContain('Maya')
        ->not->toContain('Bank Transfer');
});

test('a new online checkout cannot start a 50 percent downpayment', function () {
    [$tourist, $booking] = securePaymongoBooking();

    $this->actingAs($tourist)
        ->post(
            route('user.bookings.payment.checkout', $booking),
            [
                'payment_method' => 'gcash',
                'payment_coverage' => 'downpayment',
                'accepted_policy' => true,
            ]
        )
        ->assertSessionHasErrors('payment_coverage');

    expect(Payment::query()->where('booking_id', $booking->id)->exists())->toBeFalse()
        ->and($booking->fresh()->booking_status)->toBe('pending')
        ->and($booking->fresh()->qr_token)->toBeNull();
});

test('a signed webhook for a different checkout session does not confirm this booking', function () {
    [$tourist, $booking] = securePaymongoBooking();

    $amount = app(
        PayMongoCheckoutService::class
    )->centavosFor($booking);

    securePendingPayment($booking, $amount);

    $session = securePaymongoSession(
        $booking,
        $amount
    );

    $session['id'] = 'cs_foreign';

    $payload = json_encode([
        'data' => [
            'type' => 'event',
            'attributes' => [
                'type' => 'checkout_session.payment.paid',
                'livemode' => false,
                'data' => $session,
            ],
        ],
    ], JSON_THROW_ON_ERROR);

    securePostWebhook(
        $this,
        $payload,
        secureWebhookHeader($payload, false)
    )->assertOk();

    expect($booking->fresh()->booking_status)->toBe(
        'pending'
    )
        ->and(
            Payment::query()
                ->where('booking_id', $booking->id)
                ->first()
                ->payment_status
        )->toBe('pending');
});

test('a signed webhook with mismatched booking reference or metadata is ignored', function () {
    [$tourist, $booking] = securePaymongoBooking();

    $amount = app(
        PayMongoCheckoutService::class
    )->centavosFor($booking);

    securePendingPayment($booking, $amount);

    foreach (['reference_number', 'metadata'] as $field) {
        $session = securePaymongoSession(
            $booking,
            $amount
        );

        if ($field === 'reference_number') {
            $session['attributes']['reference_number'] =
                'HS-999999';
        } else {
            $session['attributes']['metadata']['booking_id'] =
                '999999';
        }

        $payload = json_encode([
            'data' => [
                'type' => 'checkout_session.payment.paid',
                'livemode' => false,
                'data' => $session,
            ],
        ], JSON_THROW_ON_ERROR);

        securePostWebhook(
            $this,
            $payload,
            secureWebhookHeader($payload, false)
        )->assertOk();
    }

    expect($booking->fresh()->booking_status)->toBe(
        'pending'
    )
        ->and(
            Payment::query()
                ->where('booking_id', $booking->id)
                ->first()
                ->payment_status
        )->toBe('pending');
});

test('a live-mode webhook must use its live signature field', function () {
    [$tourist, $booking] = securePaymongoBooking();

    $amount = app(
        PayMongoCheckoutService::class
    )->centavosFor($booking);

    securePendingPayment($booking, $amount);

    $payload = securePaymongoWebhook(
        $booking,
        $amount,
        true
    );

    securePostWebhook(
        $this,
        $payload,
        secureWebhookHeader($payload, false)
    )->assertForbidden();

    expect($booking->fresh()->booking_status)->toBe(
        'pending'
    );

    securePostWebhook(
        $this,
        $payload,
        secureWebhookHeader($payload, true)
    )->assertOk();

    expect($booking->fresh()->booking_status)->toBe(
        'confirmed'
    );
});

test('a pending checkout is reused and is not overwritten by another checkout request', function () {
    [$tourist, $booking] = securePaymongoBooking();

    $amount = app(
        PayMongoCheckoutService::class
    )->centavosFor($booking);

    $paymentId = securePendingPayment(
        $booking,
        $amount
    );

    Http::fake([
        'https://api.paymongo.com/v1/checkout_sessions/*' => Http::response([
            'data' => [
                'id' => 'cs_test_123',
                'attributes' => [
                    'checkout_url' => 'https://checkout.paymongo.com/cs_test_123',
                ],
            ],
        ]),
    ]);

    $this->actingAs($tourist)
        ->post(
            route('user.bookings.payment.checkout', $booking),
            [
                'payment_method' => 'gcash',
                'payment_coverage' => 'full',
                'accepted_policy' => true,
            ]
        )
        ->assertRedirect(
            'https://checkout.paymongo.com/cs_test_123'
        );

    expect(
        Payment::query()
            ->where('booking_id', $booking->id)
            ->count()
    )->toBe(1)
        ->and(
            Payment::query()
                ->findOrFail($paymentId)
                ->payment_status
        )->toBe('pending');

    Http::assertNotSent(
        fn ($request) => $request->method() === 'POST'
    );
});

test('a cancelled booking is not confirmed by a late webhook', function () {
    [$tourist, $booking] = securePaymongoBooking();

    $amount = app(
        PayMongoCheckoutService::class
    )->centavosFor($booking);

    securePendingPayment($booking, $amount);

    $booking->update([
        'booking_status' => 'cancelled',
    ]);

    $payload = securePaymongoWebhook(
        $booking,
        $amount,
        false
    );

    securePostWebhook(
        $this,
        $payload,
        secureWebhookHeader($payload, false)
    )->assertOk();

    expect($booking->fresh()->booking_status)->toBe(
        'cancelled'
    )
        ->and(
            Payment::query()
                ->where('booking_id', $booking->id)
                ->first()
                ->payment_status
        )->toBe('paid');
});

test('an incident report does not refund or change a recorded payment', function () {
    [$tourist, $booking] = securePaymongoBooking();

    $booking->update(['booking_status' => 'confirmed']);

    $payment = Payment::query()->create([
        'booking_id' => $booking->id,
        'amount' => $booking->packageTotal(),
        'payment_method' => 'gcash',
        'gateway' => 'paymongo',
        'payment_status' => 'paid',
        'paid_at' => now(),
    ]);

    $this->actingAs($tourist)
        ->post(route('user.bookings.cases.store', $booking), [
            'reason' => 'operator_no_show',
            'requested_resolution' => 'refund',
            'details' => 'The boat did not arrive at the pickup station.',
        ])
        ->assertRedirect();

    expect($payment->fresh()->payment_status)->toBe('paid')
        ->and((float) $payment->fresh()->amount)->toBe((float) $payment->amount)
        ->and($booking->fresh()->booking_status)->toBe('confirmed');
});

/*
|--------------------------------------------------------------------------
| Test helpers
|--------------------------------------------------------------------------
*/

/** @return array{0: User, 1: Booking} */
function securePaymongoBooking(): array
{
    $role = Role::query()->firstOrCreate([
        'name' => 'User',
    ]);

    $tourist = User::factory()->create([
        'role_id' => $role->id,
    ]);

    $operatorId = DB::table('tour_operators')->insertGetId([
        'name' => 'Checkout Test Operator',
        'slug' => 'checkout-test-'.Str::uuid(),
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $packageId = DB::table('packages')->insertGetId([
        'tour_operator_id' => $operatorId,
        'package_name' => 'Checkout Island Tour',
        'boat_name' => 'Checkout Boat',
        'destination' => 'Daku Island',
        'price' => '1500.00',
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $dateId = DB::table('tour_dates')->insertGetId([
        'package_id' => $packageId,
        'tour_date' => today()->addDays(12)->toDateString(),
        'capacity' => 20,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $locationId = DB::table('pickup_locations')->insertGetId([
        'name' => 'Checkout Pier',
        'tour_operator_id' => $operatorId,
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $pickupId = DB::table('pickup_schedules')->insertGetId([
        'tour_date_id' => $dateId,
        'pickup_location_id' => $locationId,
        'pickup_time' => '07:00:00',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $booking = Booking::query()->create([
        'user_id' => $tourist->id,
        'tour_date_id' => $dateId,
        'pickup_schedule_id' => $pickupId,
        'number_of_guests' => 2,
        'phone_number' => '0918'.random_int(1000000, 9999999),
        'nationality' => 'Filipino',
        'booking_status' => 'pending',
    ]);

    return [
        $tourist,
        $booking->fresh([
            'tourDate.package',
            'user',
        ]),
    ];
}

function securePendingPayment(
    Booking $booking,
    int $amount
): int {
    return Payment::query()->create([
        'booking_id' => $booking->id,
        'amount' => $amount / 100,
        'payment_method' => 'gcash',
        'gateway' => 'paymongo',
        'gateway_checkout_id' => 'cs_test_123',
        'payment_status' => 'pending',
        'transaction_reference' => 'HS-'.$booking->id,
    ])->id;
}

/** @return array<string, mixed> */
function securePaymongoSession(
    Booking $booking,
    int $amount,
    ?int $netAmount = null
): array {
    return [
        'id' => 'cs_test_123',
        'type' => 'checkout_session',
        'attributes' => [
            'reference_number' => 'HS-'.$booking->id,
            'metadata' => [
                'booking_id' => (string) $booking->id,
            ],
            'payments' => [[
                'id' => 'pay_test_123',
                'attributes' => [
                    'status' => 'paid',
                    'amount' => $amount,
                    'currency' => 'PHP',
                    'net_amount' => $netAmount
                        ?? max(0, $amount - 500),
                ],
            ]],
        ],
    ];
}

function secureFakeCheckout(
    Booking $booking,
    int $amount
): void {
    Http::fake([
        'https://api.paymongo.com/v2/checkout_sessions' => Http::response([
            'data' => [
                'id' => 'cs_test_123',
                'attributes' => [
                    'checkout_url' => 'https://checkout.paymongo.com/cs_test_123',
                ],
            ],
        ]),

        'https://api.paymongo.com/v1/checkout_sessions/*' => Http::response([
            'data' => securePaymongoSession(
                $booking,
                $amount
            ),
        ]),
    ]);
}

function securePaymongoWebhook(
    Booking $booking,
    int $amount,
    bool $live
): string {
    return json_encode([
        'data' => [
            'type' => 'event',
            'attributes' => [
                'type' => 'checkout_session.payment.paid',
                'livemode' => $live,
                'data' => securePaymongoSession(
                    $booking,
                    $amount
                ),
            ],
        ],
    ], JSON_THROW_ON_ERROR);
}

function secureWebhookHeader(
    string $payload,
    bool $live
): string {
    $timestamp = (string) time();

    $signature = hash_hmac(
        'sha256',
        $timestamp.'.'.$payload,
        'whsec_test'
    );

    return 't='.$timestamp
        .',te='.($live ? '' : $signature)
        .',li='.($live ? $signature : '');
}

function securePostWebhook(
    $test,
    string $payload,
    string $signature
) {
    return $test->call(
        'POST',
        '/paymongo/webhook',
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_PAYMONGO_SIGNATURE' => $signature,
        ],
        $payload
    );
}
