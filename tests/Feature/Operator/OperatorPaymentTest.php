
<?php

use App\Models\Role;
use App\Models\TourOperator;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    config()->set('inertia.ssr.enabled', false);
    $this->withoutVite();
});

function stageNineOperator(string $name): TourOperator
{
    return TourOperator::create([
        'name' => $name,
        'slug' => 'stage-nine-'.Str::uuid(),
        'is_active' => true,
    ]);
}

function stageNineUser(
    string $role,
    ?TourOperator $operator = null
): User {
    $roleId = Role::firstOrCreate([
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
 * Create an isolated booking.
 *
 * Package: PHP 1,500 per guest.
 * Guests: 2.
 * Total: PHP 3,000.
 *
 * @return array{
 *     booking: int,
 *     package: int,
 *     tour_date: int
 * }
 */
function stageNineBooking(
    TourOperator $operator,
    string $status = 'confirmed'
): array {
    $packageId = DB::table('packages')->insertGetId([
        'tour_operator_id' => $operator->id,
        'package_name' => 'Stage Nine Tour',
        'boat_name' => 'Boat A',
        'destination' => 'Daku Island',
        'price' => 1500,
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $dateId = DB::table('tour_dates')->insertGetId([
        'package_id' => $packageId,
        'tour_date' => today()->addDays(4)->toDateString(),
        'capacity' => 20,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $pickupId = DB::table('pickup_locations')->insertGetId([
        'tour_operator_id' => $operator->id,
        'name' => 'Stage Nine Pier '.Str::random(5),
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $pickupScheduleId = DB::table('pickup_schedules')
        ->insertGetId([
            'tour_date_id' => $dateId,
            'pickup_location_id' => $pickupId,
            'pickup_time' => '07:00:00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

    $tourist = stageNineUser('User');

    $bookingId = DB::table('bookings')->insertGetId([
        'user_id' => $tourist->id,
        'tour_date_id' => $dateId,
        'pickup_schedule_id' => $pickupScheduleId,
        'number_of_guests' => 2,
        'phone_number' => '09123456789',
        'nationality' => 'Filipino',
        'booking_status' => $status,
        'qr_token' => (string) Str::uuid(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return [
        'booking' => $bookingId,
        'package' => $packageId,
        'tour_date' => $dateId,
    ];
}

function stageNineDownpayment(
    int $bookingId,
    string $gateway = 'paymongo'
): int {
    return DB::table('payments')->insertGetId([
        'booking_id' => $bookingId,
        'amount' => 1500,
        'payment_method' => 'gcash',
        'gateway' => $gateway,
        'payment_status' => 'partial',
        'paid_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function stageNineCash(
    int $bookingId,
    string $key,
    string $amount = '1500.00'
): array {
    return [
        'amount' => $amount,
        'collection_key' => $key,
        'cash_received' => true,
    ];
}

/*
|--------------------------------------------------------------------------
| TEST 1 — PAYMENT ISOLATION
|--------------------------------------------------------------------------
*/

test('operator sees only their company payment transactions', function () {
    $a = stageNineOperator('A');
    $b = stageNineOperator('B');

    $own = stageNineBooking($a);
    $foreign = stageNineBooking($b);

    stageNineDownpayment($own['booking']);
    stageNineDownpayment($foreign['booking']);

    $this->actingAs(
        stageNineUser('Admin', $a)
    )
        ->get(
            route('operator.payments.index')
        )
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('operator/Payments')
                ->where('payments.total', 1)
                ->where(
                    'payments.data.0.booking_id',
                    $own['booking']
                )
        );

    $this->get(
        route(
            'operator.payments.show',
            $foreign['booking']
        )
    )->assertNotFound();
});

/*
|--------------------------------------------------------------------------
| TEST 2 — DOWNPAYMENT AND REMAINING BALANCE
|--------------------------------------------------------------------------
*/

test('operator sees downpayment and remaining balance for own booking', function () {
    $operator = stageNineOperator('A');

    $booking = stageNineBooking($operator)['booking'];

    stageNineDownpayment($booking);

    $this->actingAs(
        stageNineUser('Admin', $operator)
    )
        ->get(
            route('operator.payments.show', $booking)
        )
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('operator/PaymentDetails')
                ->where(
                    'booking.total',
                    fn ($amount) => (float) $amount === 3000.0
                )
                ->where(
                    'booking.received',
                    fn ($amount) => (float) $amount === 1500.0
                )
                ->where(
                    'booking.remaining',
                    fn ($amount) => (float) $amount === 1500.0
                )
                ->where(
                    'booking.canCollect',
                    true
                )
        );
});

/*
|--------------------------------------------------------------------------
| TEST 3 — CASH COLLECTION AND PAYMENT HISTORY
|--------------------------------------------------------------------------
*/

test('operator records cash balance until the booking is settled', function () {
    $operator = stageNineOperator('A');

    $booking = stageNineBooking($operator)['booking'];

    $downpayment = stageNineDownpayment($booking);

    $this->actingAs(
        stageNineUser('Admin', $operator)
    );

    // Collect PHP 600 first.
    $this->post(
        route('operator.payments.collect', $booking),
        stageNineCash(
            $booking,
            (string) Str::uuid(),
            '600.00'
        )
    )->assertRedirect(
        route('operator.payments.show', $booking)
    );

    // Collect the remaining PHP 900.
    $this->post(
        route('operator.payments.collect', $booking),
        stageNineCash(
            $booking,
            (string) Str::uuid(),
            '900.00'
        )
    )->assertRedirect(
        route('operator.payments.show', $booking)
    );

    // Cash total must be exactly PHP 1,500.
    $cashReceived = (float) DB::table('payments')
        ->where('booking_id', $booking)
        ->where('payment_method', 'cash')
        ->sum('amount');

    expect($cashReceived)->toBe(1500.0);

    // There must be exactly two separate cash records.
    $cashTransactions = DB::table('payments')
        ->where('booking_id', $booking)
        ->where('payment_method', 'cash')
        ->count();

    expect($cashTransactions)->toBe(2);

    /*
     * The original PayMongo transaction
     * must not be rewritten as paid.
     */
    $this->assertDatabaseHas('payments', [
        'id' => $downpayment,
        'booking_id' => $booking,
        'gateway' => 'paymongo',
        'payment_method' => 'gcash',
        'amount' => 1500,
        'payment_status' => 'partial',
    ]);

    // The booking itself is now fully settled.
    $this->get(
        route('operator.payments.show', $booking)
    )
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('operator/PaymentDetails')
                ->where(
                    'booking.remaining',
                    fn ($amount) => (float) $amount === 0.0
                )
                ->where('booking.canCollect', false)
        );
});

/*
|--------------------------------------------------------------------------
| TEST 4 — DUPLICATE SUBMISSIONS
|--------------------------------------------------------------------------
*/

test('replaying one cash collection cannot create duplicate payment', function () {
    $operator = stageNineOperator('A');

    $booking = stageNineBooking($operator)['booking'];

    stageNineDownpayment($booking);

    // Simulate the same cash form being submitted twice.
    $payload = stageNineCash(
        $booking,
        (string) Str::uuid()
    );

    $this->actingAs(
        stageNineUser('Admin', $operator)
    );

    $this->post(
        route('operator.payments.collect', $booking),
        $payload
    )->assertRedirect();

    $this->post(
        route('operator.payments.collect', $booking),
        $payload
    )->assertRedirect();

    $cashTransactions = DB::table('payments')
        ->where('booking_id', $booking)
        ->where('payment_method', 'cash')
        ->count();

    expect($cashTransactions)->toBe(1);
});

/*
|--------------------------------------------------------------------------
| TEST 5 — OVERPAYMENT PROTECTION
|--------------------------------------------------------------------------
*/

test('overpayment and missing cash confirmation are rejected', function () {
    $operator = stageNineOperator('A');

    $booking = stageNineBooking($operator)['booking'];

    stageNineDownpayment($booking);

    $this->actingAs(
        stageNineUser('Admin', $operator)
    );

    // PHP 1,500.01 exceeds the remaining PHP 1,500.
    $this->post(
        route('operator.payments.collect', $booking),
        stageNineCash(
            $booking,
            (string) Str::uuid(),
            '1500.01'
        )
    )->assertSessionHasErrors('amount');

    // Cash confirmation is mandatory.
    $payload = stageNineCash(
        $booking,
        (string) Str::uuid()
    );

    $payload['cash_received'] = false;

    $this->post(
        route('operator.payments.collect', $booking),
        $payload
    )->assertSessionHasErrors('cash_received');

    $cashTransactions = DB::table('payments')
        ->where('booking_id', $booking)
        ->where('payment_method', 'cash')
        ->count();

    expect($cashTransactions)->toBe(0);
});

/*
|--------------------------------------------------------------------------
| TEST 6 — FOREIGN AND INVALID BOOKINGS
|--------------------------------------------------------------------------
*/

test('foreign cancelled and unverified bookings cannot collect cash balance', function () {
    $a = stageNineOperator('A');
    $b = stageNineOperator('B');

    $foreign = stageNineBooking($b)['booking'];
    stageNineDownpayment($foreign);

    $cancelled = stageNineBooking(
        $a,
        'cancelled'
    )['booking'];

    stageNineDownpayment($cancelled);

    $unverified = stageNineBooking($a)['booking'];

    stageNineDownpayment(
        $unverified,
        'unverified'
    );

    $this->actingAs(
        stageNineUser('Admin', $a)
    );

    $this->post(
        route('operator.payments.collect', $foreign),
        stageNineCash(
            $foreign,
            (string) Str::uuid()
        )
    )->assertNotFound();

    $this->post(
        route('operator.payments.collect', $cancelled),
        stageNineCash(
            $cancelled,
            (string) Str::uuid()
        )
    )->assertSessionHasErrors('amount');

    $this->post(
        route('operator.payments.collect', $unverified),
        stageNineCash(
            $unverified,
            (string) Str::uuid()
        )
    )->assertSessionHasErrors('amount');

    $cashCount = DB::table('payments')
        ->whereIn(
            'booking_id',
            [
                $foreign,
                $cancelled,
                $unverified,
            ]
        )
        ->where('payment_method', 'cash')
        ->count();

    expect($cashCount)->toBe(0);
});

/*
|--------------------------------------------------------------------------
| TEST 7 — LEGACY PAYMENT ROUTES
|--------------------------------------------------------------------------
*/

test('operator cannot use old shared payment or balance endpoints', function () {
    $operator = stageNineOperator('A');

    $booking = stageNineBooking($operator)['booking'];

    stageNineDownpayment($booking);

    $this->actingAs(
        stageNineUser('Admin', $operator)
    )
        ->get(
            route('admin.payments.index')
        )
        ->assertForbidden();

    $this->get(
        route('admin.payments.data')
    )->assertForbidden();

    $this->post(
        route('admin.bookings.balance', $booking),
        ['amount' => 500]
    )->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| TEST 8 — ROLE PROTECTION
|--------------------------------------------------------------------------
*/

test('tourists and super admins cannot use operator payment routes', function () {
    $operator = stageNineOperator('A');

    $booking = stageNineBooking($operator)['booking'];

    foreach (['User', 'Superadmin'] as $role) {
        $this->actingAs(
            stageNineUser($role)
        )
            ->get(
                route('operator.payments.index')
            )
            ->assertForbidden();

        $this->post(
            route('operator.payments.collect', $booking),
            stageNineCash(
                $booking,
                (string) Str::uuid()
            )
        )->assertForbidden();
    }
});

/*
|--------------------------------------------------------------------------
| TEST 9 — COLLECTING AFTER FULL SETTLEMENT
|--------------------------------------------------------------------------
*/

test('a new cash collection after full settlement is rejected', function () {
    $operator = stageNineOperator('A');

    $booking = stageNineBooking($operator)['booking'];

    stageNineDownpayment($booking);

    $this->actingAs(
        stageNineUser('Admin', $operator)
    );

    // Collect the full PHP 1,500 balance.
    $this->post(
        route('operator.payments.collect', $booking),
        stageNineCash(
            $booking,
            (string) Str::uuid()
        )
    )->assertRedirect();

    // A new payment reference cannot collect more.
    $this->post(
        route('operator.payments.collect', $booking),
        stageNineCash(
            $booking,
            (string) Str::uuid(),
            '0.01'
        )
    )->assertSessionHasErrors('amount');

    $cashTransactions = DB::table('payments')
        ->where('booking_id', $booking)
        ->where('payment_method', 'cash')
        ->count();

    expect($cashTransactions)->toBe(1);
});

/*
|--------------------------------------------------------------------------
| TEST 10 — MISSING PAYMENT CONFIRMATION DATE
|--------------------------------------------------------------------------
*/

test('an unpaid pending downpayment cannot authorize cash collection', function () {
    $operator = stageNineOperator('A');

    $booking = stageNineBooking($operator)['booking'];

    $paymentId = stageNineDownpayment($booking);

    /*
     * Simulate an incomplete payment confirmation.
     * The partial payment has no paid_at timestamp.
     */
    DB::table('payments')
        ->where('id', $paymentId)
        ->update([
            'paid_at' => null,
        ]);

    $this->actingAs(
        stageNineUser('Admin', $operator)
    )
        ->get(
            route('operator.payments.show', $booking)
        )
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('operator/PaymentDetails')
                ->where(
                    'booking.canCollect',
                    false
                )
        );

    $this->post(
        route('operator.payments.collect', $booking),
        stageNineCash(
            $booking,
            (string) Str::uuid()
        )
    )->assertSessionHasErrors('amount');

    $cashCount = DB::table('payments')
        ->where('booking_id', $booking)
        ->where('payment_method', 'cash')
        ->count();

    expect($cashCount)->toBe(0);
});

test('direct mark refunded is blocked so operators must use refund management', function () {
    $operator = stageNineOperator('Refund Owner');
    $own = stageNineBooking($operator);

    $paidId = DB::table('payments')->insertGetId([
        'booking_id' => $own['booking'],
        'amount' => 3000,
        'payment_method' => 'gcash',
        'gateway' => 'paymongo',
        'payment_status' => 'paid',
        'paid_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $admin = stageNineUser('Admin', $operator);

    $this->actingAs($admin)
        ->post(route('operator.payments.refund', $paidId))
        ->assertForbidden();

    expect(DB::table('payments')->where('id', $paidId)->value('payment_status'))->toBe('paid');

    $paymentsUi = file_get_contents(resource_path('js/pages/operator/Payments.vue'));
    $detailsUi = file_get_contents(resource_path('js/pages/operator/PaymentDetails.vue'));

    expect($paymentsUi)->not->toContain('Mark refunded')
        ->and($detailsUi)->not->toContain('Mark refunded');
});
