
<?php

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Role;
use App\Models\TourOperator;
use App\Models\User;
use App\Notifications\BookingCancelledNotification;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    $this->withoutVite();
    Notification::fake();
    Http::preventStrayRequests();

    config([
        'payment.paymongo.secret_key' => 'sk_test_example',
        'payment.paymongo.webhook_secret' => 'whsec_test',
    ]);
});

function lifecycleUser(
    string $role,
    ?TourOperator $operator = null
): User {
    $roleId = Role::query()
        ->firstOrCreate([
            'name' => $role,
        ])->id;

    $user = User::factory()->create([
        'role_id' => $roleId,
    ]);

    if ($operator !== null) {
        $user->forceFill([
            'tour_operator_id' => $operator->id,
        ])->save();
    }

    return $user->refresh();
}

/**
 * @return array{
 *     tourist: User,
 *     operator: TourOperator,
 *     booking: Booking
 * }
 */
function lifecycleBooking(
    string $status = 'pending'
): array {
    $operator = TourOperator::query()->create([
        'name' => 'Lifecycle Test',
        'slug' => 'lifecycle-'.Str::uuid(),
        'is_active' => true,
    ]);

    $packageId = DB::table('packages')
        ->insertGetId([
            'tour_operator_id' => $operator->id,
            'package_name' => 'Lifecycle Island Tour',
            'boat_name' => 'Lifecycle Boat',
            'destination' => 'Daku Island',
            'price' => 1500,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

    $dateId = DB::table('tour_dates')
        ->insertGetId([
            'package_id' => $packageId,
            'tour_date' => today()
                ->addDays(15)
                ->toDateString(),
            'capacity' => 20,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

    $locationId = DB::table('pickup_locations')
        ->insertGetId([
            'tour_operator_id' => $operator->id,
            'name' => 'Pier '.Str::random(9),
            'latitude' => 9.86,
            'longitude' => 126.08,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

    $pickupId = DB::table('pickup_schedules')
        ->insertGetId([
            'tour_date_id' => $dateId,
            'pickup_location_id' => $locationId,
            'pickup_time' => '07:00:00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

    $tourist = lifecycleUser('User');

    $booking = Booking::query()->create([
        'user_id' => $tourist->id,
        'tour_date_id' => $dateId,
        'pickup_schedule_id' => $pickupId,
        'number_of_guests' => 2,
        'phone_number' => '09123456789',
        'nationality' => 'Filipino',
        'booking_status' => $status,
    ]);

    return compact(
        'tourist',
        'operator',
        'booking'
    );
}

function lifecyclePayment(
    Booking $booking,
    string $status = 'pending'
): Payment {
    return Payment::query()->create([
        'booking_id' => $booking->id,
        'amount' => 1500,
        'payment_method' => 'gcash',
        'gateway' => 'paymongo',
        'gateway_checkout_id' => 'cs_test_'.Str::random(15),
        'payment_status' => $status,
        'transaction_reference' => $status === 'pending'
                ? null
                : 'pay_test_'.Str::random(12),
        'paid_at' => in_array(
            $status,
            ['paid', 'partial'],
            true
        ) ? now() : null,
    ]);
}

function lifecycleSession(
    Booking $booking,
    Payment $payment,
    string $status,
    bool $paid = false
): array {
    return [
        'id' => $payment->gateway_checkout_id,
        'attributes' => [
            'status' => $status,
            'checkout_url' => 'https://checkout.paymongo.com/'
                .$payment->gateway_checkout_id,
            'reference_number' => 'HS-'.$booking->id,
            'metadata' => [
                'booking_id' => (string) $booking->id,
            ],
            'payments' => $paid
                ? [[
                    'id' => 'pay_test_'.Str::random(12),
                    'attributes' => [
                        'status' => 'paid',
                        'amount' => 150000,
                        'currency' => 'PHP',
                        'net_amount' => 145000,
                    ],
                ]]
                : [],
        ],
    ];
}

function lifecycleFakeSession(
    array $session,
    int $expireHttp = 200
): void {
    Http::fake([
        'https://api.paymongo.com/v1/checkout_sessions/*/expire' => Http::response(
            ['data' => $session],
            $expireHttp
        ),

        'https://api.paymongo.com/v1/checkout_sessions/*' => Http::response(
            ['data' => $session],
            200
        ),
    ]);
}

function lifecycleWebhook(
    array $session
): array {
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

    $stamp = (string) time();

    return [
        $payload,
        't='.$stamp
            .',te='.hash_hmac(
                'sha256',
                $stamp.'.'.$payload,
                'whsec_test'
            )
            .',li=',
    ];
}

/*
|--------------------------------------------------------------------------
| TEST 1 — NORMAL CANCELLATION
|--------------------------------------------------------------------------
*/

test('unpaid pending booking cancels and notifies only its operator and superadmin', function () {
    $data = lifecycleBooking();

    $ownerAdmin = lifecycleUser(
        'Admin',
        $data['operator']
    );

    $otherOperator = lifecycleBooking()['operator'];

    $otherAdmin = lifecycleUser(
        'Admin',
        $otherOperator
    );

    $superadmin = lifecycleUser('Superadmin');

    $this->actingAs(
        $data['tourist'],
        'web'
    )
        ->patch(
            route(
                'user.bookings.cancel',
                $data['booking']
            )
        )
        ->assertRedirect(
            route('user.history')
        );

    expect(
        $data['booking']->fresh()->booking_status
    )->toBe('cancelled');

    Notification::assertSentTo(
        $ownerAdmin,
        BookingCancelledNotification::class
    );

    Notification::assertSentTo(
        $superadmin,
        BookingCancelledNotification::class
    );

    Notification::assertNotSentTo(
        $otherAdmin,
        BookingCancelledNotification::class
    );
});

/*
|--------------------------------------------------------------------------
| TEST 2 — ACTIVE CHECKOUT
|--------------------------------------------------------------------------
*/

test('active PayMongo checkout cannot be cancelled', function () {
    $data = lifecycleBooking();
    $payment = lifecyclePayment($data['booking']);

    lifecycleFakeSession(
        lifecycleSession(
            $data['booking'],
            $payment,
            'active'
        ),
        400
    );

    $this->actingAs(
        $data['tourist'],
        'web'
    )
        ->patch(
            route(
                'user.bookings.cancel',
                $data['booking']
            )
        )
        ->assertRedirect(
            route('user.bookings.index')
        );

    expect(
        $data['booking']->fresh()->booking_status
    )->toBe('pending')
        ->and(
            $payment->fresh()->payment_status
        )->toBe('pending');

    Notification::assertNothingSent();
});

/*
|--------------------------------------------------------------------------
| TEST 3 — VERIFIED EXPIRED CHECKOUT
|--------------------------------------------------------------------------
*/

test('verified expired checkout permits cancellation but retains the ledger', function () {
    $data = lifecycleBooking();
    $payment = lifecyclePayment($data['booking']);

    lifecycleFakeSession(
        lifecycleSession(
            $data['booking'],
            $payment,
            'expired'
        )
    );

    $this->actingAs(
        $data['tourist'],
        'web'
    )
        ->patch(
            route(
                'user.bookings.cancel',
                $data['booking']
            )
        )
        ->assertRedirect(
            route('user.history')
        );

    expect(
        $data['booking']->fresh()->booking_status
    )->toBe('cancelled')
        ->and(
            $payment->fresh()->payment_status
        )->toBe('cancelled')
        ->and(
            $payment->fresh()->paid_at
        )->toBeNull()
        ->and(
            $payment->fresh()->notes
        )->toStartWith(
            'Verified PayMongo checkout expired'
        );

    $this->assertDatabaseHas('payments', [
        'id' => $payment->id,
    ]);
});

/*
|--------------------------------------------------------------------------
| TEST 4 — PAYMENT BEFORE CANCELLATION
|--------------------------------------------------------------------------
*/

test('payment completed during cancellation is confirmed instead of cancelled', function () {
    $data = lifecycleBooking();
    $payment = lifecyclePayment($data['booking']);

    lifecycleFakeSession(
        lifecycleSession(
            $data['booking'],
            $payment,
            'active',
            true
        ),
        400
    );

    $this->actingAs(
        $data['tourist'],
        'web'
    )
        ->patch(
            route(
                'user.bookings.cancel',
                $data['booking']
            )
        )
        ->assertRedirect(
            route('user.bookings.index')
        );

    expect(
        $data['booking']->fresh()->booking_status
    )->toBe('confirmed')
        ->and(
            $payment->fresh()->payment_status
        )->toBe('partial');
});

/*
|--------------------------------------------------------------------------
| TEST 5 — PAYMONGO ERROR
|--------------------------------------------------------------------------
*/

test('PayMongo outage cannot be interpreted as proof of expiry', function () {
    $data = lifecycleBooking();
    $payment = lifecyclePayment($data['booking']);

    Http::fake([
        'https://api.paymongo.com/v1/checkout_sessions/*/expire' => Http::response([], 500),

        'https://api.paymongo.com/v1/checkout_sessions/*' => Http::response([], 500),
    ]);

    $this->actingAs(
        $data['tourist'],
        'web'
    )
        ->patch(
            route(
                'user.bookings.cancel',
                $data['booking']
            )
        )
        ->assertRedirect(
            route('user.bookings.index')
        );

    expect(
        $data['booking']->fresh()->booking_status
    )->toBe('pending')
        ->and(
            $payment->fresh()->payment_status
        )->toBe('pending');
});

/*
|--------------------------------------------------------------------------
| TEST 6 — EXPIRED CHECKOUT RECOVERY
|--------------------------------------------------------------------------
*/

test('expired previous checkout can be replaced with a new payment session', function () {
    $data = lifecycleBooking();
    $old = lifecyclePayment($data['booking']);

    $session = lifecycleSession(
        $data['booking'],
        $old,
        'expired'
    );

    Http::fake([
        'https://api.paymongo.com/v1/checkout_sessions/*' => Http::response([
            'data' => $session,
        ]),

        'https://api.paymongo.com/v2/checkout_sessions' => Http::response([
            'data' => [
                'id' => 'cs_new_'.Str::random(9),
                'attributes' => [
                    'checkout_url' => 'https://checkout.paymongo.com/cs_new_safe',
                ],
            ],
        ]),
    ]);

    $this->actingAs(
        $data['tourist'],
        'web'
    )
        ->post(
            route(
                'user.bookings.payment.checkout',
                $data['booking']
            ),
            [
                'payment_method' => 'gcash',
                'payment_coverage' => 'full',
                'accepted_policy' => true,
            ]
        )
        ->assertRedirect(
            'https://checkout.paymongo.com/cs_new_safe'
        );

    expect(
        $old->fresh()->payment_status
    )->toBe('cancelled')
        ->and(
            $data['booking']->fresh()->booking_status
        )->toBe('pending')
        ->and(
            Payment::query()
                ->where(
                    'booking_id',
                    $data['booking']->id
                )
                ->where(
                    'payment_status',
                    'pending'
                )
                ->count()
        )->toBe(1);
});

/*
|--------------------------------------------------------------------------
| TEST 7 — EXISTING PAYMENT PROTECTION
|--------------------------------------------------------------------------
*/

test('recorded downpayment cannot be cancelled or deleted', function () {
    $data = lifecycleBooking();

    $payment = lifecyclePayment(
        $data['booking'],
        'partial'
    );

    $this->actingAs(
        $data['tourist'],
        'web'
    )
        ->patch(
            route(
                'user.bookings.cancel',
                $data['booking']
            )
        )
        ->assertRedirect(
            route('user.bookings.index')
        );

    expect(
        $payment->fresh()->payment_status
    )->toBe('partial')
        ->and(
            $data['booking']->fresh()->booking_status
        )->toBe('pending');

    $this->delete(
        route(
            'user.bookings.destroy',
            $data['booking']
        )
    )
        ->assertRedirect(
            route('user.history')
        );

    $this->assertDatabaseHas('payments', [
        'id' => $payment->id,
    ]);

    $this->assertDatabaseHas('bookings', [
        'id' => $data['booking']->id,
    ]);
});

/*
|--------------------------------------------------------------------------
| TEST 8 — LATE PAYMENT WEBHOOK
|--------------------------------------------------------------------------
*/

test('late paid webhook on cancelled booking becomes an idempotent reconciliation case', function () {
    $data = lifecycleBooking('cancelled');

    $payment = lifecyclePayment(
        $data['booking']
    );

    $session = lifecycleSession(
        $data['booking'],
        $payment,
        'active',
        true
    );

    [
        $payload,
        $signature
    ] = lifecycleWebhook($session);

    $this->actingAs(
        $data['tourist'],
        'web'
    );

    // Simulate the same webhook twice.
    for ($i = 0; $i < 2; $i++) {
        $this->call(
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
        )->assertOk();
    }

    expect(
        $data['booking']->fresh()->booking_status
    )->toBe('cancelled')
        ->and(
            $payment->fresh()->payment_status
        )->toBe('partial')
        ->and(
            DB::table(
                'payment_reconciliation_cases'
            )
                ->where(
                    'booking_id',
                    $data['booking']->id
                )
                ->count()
        )->toBe(1);

    $this->assertDatabaseHas(
        'payment_reconciliation_cases',
        [
            'booking_id' => $data['booking']->id,
            'reason' => 'paid_after_cancellation',
            'status' => 'needs_review',
        ]
    );
});

/*
|--------------------------------------------------------------------------
| TEST 9 — UNMATCHED EXTERNAL CHECKOUT
|--------------------------------------------------------------------------
*/

test('unmatched paid session is flagged but never confirms a cancelled booking', function () {
    $data = lifecycleBooking('cancelled');

    $unrelated = lifecyclePayment(
        $data['booking']
    );

    $session = lifecycleSession(
        $data['booking'],
        $unrelated,
        'active',
        true
    );

    // Valid booking reference but different checkout ID.
    $session['id'] = 'cs_missing_'.Str::random(10);

    [
        $payload,
        $signature
    ] = lifecycleWebhook($session);

    $this->call(
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
    )->assertOk();

    expect(
        $data['booking']->fresh()->booking_status
    )->toBe('cancelled')
        ->and(
            $unrelated->fresh()->payment_status
        )->toBe('pending');

    $this->assertDatabaseHas(
        'payment_reconciliation_cases',
        [
            'booking_id' => $data['booking']->id,
            'reason' => 'unmatched_checkout',
            'status' => 'needs_review',
        ]
    );
});

/*
|--------------------------------------------------------------------------
| TEST 10 — TOURIST OWNERSHIP
|--------------------------------------------------------------------------
*/

test('another tourist cannot cancel or permanently delete the booking', function () {
    $data = lifecycleBooking();

    $other = lifecycleUser('User');

    $this->actingAs(
        $other,
        'web'
    )
        ->patch(
            route(
                'user.bookings.cancel',
                $data['booking']
            )
        )
        ->assertForbidden();

    $this->delete(
        route(
            'user.bookings.destroy',
            $data['booking']
        )
    )->assertForbidden();

    expect(
        $data['booking']->fresh()->booking_status
    )->toBe('pending');
});

/*
|--------------------------------------------------------------------------
| TEST 11 — WRONG PAYMONGO SESSION ID
|--------------------------------------------------------------------------
*/

test('expired checkout replacement requires exact verified remote session ID', function () {
    $data = lifecycleBooking();

    $payment = lifecyclePayment(
        $data['booking']
    );

    $wrong = lifecycleSession(
        $data['booking'],
        $payment,
        'expired'
    );

    $wrong['id'] = 'cs_wrong';

    lifecycleFakeSession($wrong);

    $this->actingAs(
        $data['tourist'],
        'web'
    )
        ->post(
            route(
                'user.bookings.payment.checkout',
                $data['booking']
            ),
            [
                'payment_method' => 'gcash',
                'payment_coverage' => 'full',
                'accepted_policy' => true,
            ]
        )
        ->assertSessionHasErrors(
            'payment_method'
        );

    expect(
        $payment->fresh()->payment_status
    )->toBe('pending');
});
