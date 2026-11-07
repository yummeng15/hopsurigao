
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
    $this->withoutVite();
});

/**
 * Create a test tour operator.
 */
function stageTenOperator(string $name): TourOperator
{
    return TourOperator::create([
        'name' => $name,
        'slug' => 'stage-ten-'.Str::uuid(),
        'is_active' => true,
    ]);
}

/**
 * Create a user with the requested role.
 */
function stageTenAccount(
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
 * Create a package, tour date, pickup location,
 * pickup schedule, and confirmed booking.
 */
function stageTenBooking(?TourOperator $operator): int
{
    $package = DB::table('packages')->insertGetId([
        'tour_operator_id' => $operator?->id,
        'package_name' => 'Stage Ten Island Tour',
        'boat_name' => 'Test Boat',
        'destination' => 'Daku Island',
        'price' => 1500,
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $date = DB::table('tour_dates')->insertGetId([
        'package_id' => $package,
        'tour_date' => today()->addDays(7)->toDateString(),
        'capacity' => 15,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $location = DB::table('pickup_locations')->insertGetId([
        'tour_operator_id' => $operator?->id,
        'name' => 'Stage Ten Pier '.Str::random(7),
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $pickup = DB::table('pickup_schedules')->insertGetId([
        'tour_date_id' => $date,
        'pickup_location_id' => $location,
        'pickup_time' => '07:30:00',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $tourist = stageTenAccount('User');

    return DB::table('bookings')->insertGetId([
        'user_id' => $tourist->id,
        'tour_date_id' => $date,
        'pickup_schedule_id' => $pickup,
        'number_of_guests' => 2,
        'phone_number' => '09123456789',
        'nationality' => 'Filipino',
        'booking_status' => 'confirmed',
        'qr_token' => (string) Str::uuid(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/**
 * Insert a payment transaction for testing.
 */
function stageTenPayment(
    int $booking,
    float $amount,
    string $method,
    string $status = 'paid',
    ?string $gateway = null,
    ?string $paidAt = null
): void {
    DB::table('payments')->insert([
        'booking_id' => $booking,
        'amount' => $amount,
        'payment_method' => $method,
        'gateway' => $gateway,
        'payment_status' => $status,
        'paid_at' => $paidAt ?? now()->toDateTimeString(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/*
|--------------------------------------------------------------------------
| TEST 1: Super Admin Can View Operator Collections
|--------------------------------------------------------------------------
*/

test('super admin sees separately attributed cash and gcash collections', function () {
    $a = stageTenOperator('Stage10 Operator A');
    $b = stageTenOperator('Stage10 Operator B');

    $bookingA = stageTenBooking($a);
    $bookingB = stageTenBooking($b);

    // Operator A: GCash downpayment.
    stageTenPayment(
        $bookingA,
        1500,
        'gcash',
        'partial',
        'paymongo'
    );

    // Operator A: successful cash collection.
    stageTenPayment(
        $bookingA,
        1000,
        'Cash'
    );

    // Pending payment must not count as collected.
    stageTenPayment(
        $bookingA,
        500,
        'cash',
        'pending'
    );

    // Operator B: separate cash collection.
    stageTenPayment(
        $bookingB,
        300,
        'Cash'
    );

    $this->actingAs(
        stageTenAccount('Superadmin'),
        'web'
    )
        ->get(
            route('superadmin.operator-finance.index', [
                'month' => now()->format('Y-m'),
            ])
        )
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('superadmin/OperatorFinance')
            ->where('month', now()->format('Y-m'))
            ->where('operators', function ($items) use ($a, $b): bool {
                $list = collect($items);

                $rowA = $list->firstWhere('id', $a->id);
                $rowB = $list->firstWhere('id', $b->id);

                expect($rowA)->not->toBeNull();
                expect($rowB)->not->toBeNull();

                expect((int) $rowA['bookings'])
                    ->toBe(1);

                expect((float) $rowA['gross_received'])
                    ->toBe(2500.0);

                expect((float) $rowA['gcash_received'])
                    ->toBe(1500.0);

                expect((float) $rowA['cash_received'])
                    ->toBe(1000.0);

                expect((float) $rowB['gross_received'])
                    ->toBe(300.0);

                return true;
            }));
});

/*
|--------------------------------------------------------------------------
| TEST 2: Monthly Payment Filtering
|--------------------------------------------------------------------------
*/

test('a payment from another month is excluded from this months collections', function () {
    $operator = stageTenOperator(
        'Stage10 Month Filter'
    );

    $booking = stageTenBooking($operator);

    /*
     * Fixed reporting dates prevent the test from
     * depending on the current system month.
     */
    $reportMonth = '2026-08';

    // August: PHP 100 should be included.
    stageTenPayment(
        $booking,
        100,
        'Cash',
        'paid',
        null,
        '2026-08-15 12:00:00'
    );

    // July: PHP 400 must be excluded.
    stageTenPayment(
        $booking,
        400,
        'Cash',
        'paid',
        null,
        '2026-07-15 12:00:00'
    );

    $this->actingAs(
        stageTenAccount('Superadmin'),
        'web'
    )
        ->get(
            route('superadmin.operator-finance.index', [
                'month' => $reportMonth,
            ])
        )
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('superadmin/OperatorFinance')
            ->where('month', $reportMonth)
            ->where('operators', function ($items) use ($operator): bool {
                $row = collect($items)->firstWhere(
                    'id',
                    $operator->id
                );

                expect($row)->not->toBeNull();

                // Only the August payment should count.
                expect((float) $row['gross_received'])
                    ->toBe(100.0);

                expect((float) $row['cash_received'])
                    ->toBe(100.0);

                // No PayMongo GCash payment was recorded.
                expect((float) $row['gcash_received'])
                    ->toBe(0.0);

                return true;
            }));
});

/*
|--------------------------------------------------------------------------
| TEST 3: Operator and Tourist Access Protection
|--------------------------------------------------------------------------
*/

test('operator and tourist cannot view platform finance overview', function () {
    $operator = stageTenOperator(
        'Stage10 Access'
    );

    $accounts = [
        stageTenAccount('Admin', $operator),
        stageTenAccount('User'),
    ];

    foreach ($accounts as $account) {
        $this->actingAs($account, 'web')
            ->get(
                route('superadmin.operator-finance.index')
            )
            ->assertForbidden();
    }
});

/*
|--------------------------------------------------------------------------
| TEST 4: Invalid Reporting Month
|--------------------------------------------------------------------------
*/

test('invalid month filter is rejected', function () {
    $this->actingAs(
        stageTenAccount('Superadmin'),
        'web'
    )
        ->get(
            route('superadmin.operator-finance.index', [
                'month' => '2026-19',
            ])
        )
        ->assertSessionHasErrors('month');
});

test('operator finance page exposes a clickable month calendar control', function () {
    $source = file_get_contents(
        resource_path('js/pages/superadmin/OperatorFinance.vue')
    );

    expect($source)
        ->toContain('type="month"')
        ->toContain('data-test="operator-finance-month-calendar"')
        ->toContain('openMonthPicker')
        ->toContain('shiftMonth(-1)')
        ->toContain('@change="onMonthPicked"');
});
