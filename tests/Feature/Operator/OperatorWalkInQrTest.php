
<?php

use App\Models\Role;
use App\Models\TourOperator;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(DatabaseTransactions::class);

beforeEach(function () {
    $this->withoutVite();
});

function stageEightOperator(
    string $name,
    bool $active = true
): TourOperator {
    return TourOperator::create([
        'name' => $name,
        'slug' => 'stage-eight-'.Str::uuid(),
        'is_active' => $active,
    ]);
}

function stageEightAccount(
    string $role,
    ?TourOperator $operator = null
): User {
    $roleId = Role::firstOrCreate([
        'name' => $role,
    ])->id;

    $user = User::factory()->create([
        'role_id' => $roleId,
    ]);

    if ($operator) {
        $user->forceFill([
            'tour_operator_id' => $operator->id,
        ])->save();
    }

    return $user->refresh();
}

/**
 * Create an isolated test package, tour date and pickup.
 *
 * @return array{package: int, date: int, pickup: int}
 */
function stageEightFixture(
    TourOperator $operator,
    array $overrides = []
): array {
    $package = DB::table('packages')->insertGetId(
        array_merge([
            'tour_operator_id' => $operator->id,
            'package_name' => 'Test Boat Tour',
            'boat_name' => 'Boat 1',
            'destination' => 'Daku Island',
            'price' => '1500.00',
            'status' => 'active',
            'under_maintenance' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides)
    );

    $date = DB::table('tour_dates')->insertGetId([
        'package_id' => $package,
        'tour_date' => today()->addDays(5)->toDateString(),
        'capacity' => 10,
        'travel_closed' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $location = DB::table('pickup_locations')->insertGetId([
        'tour_operator_id' => $operator->id,
        'name' => 'Stage Eight Pier '.Str::random(5),
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $pickup = DB::table('pickup_schedules')->insertGetId([
        'tour_date_id' => $date,
        'pickup_location_id' => $location,
        'pickup_time' => '07:00:00',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return compact('package', 'date', 'pickup');
}

function stageEightWalkInPayload(array $fixture): array
{
    return [
        'customer_name' => 'Walkin Test Guest',
        'email' =>
            'walkin-'.Str::lower(Str::random(10)).'@example.test',
        'phone_number' => '09123456789',
        'nationality' => 'Filipino',
        'tour_date_id' => $fixture['date'],
        'pickup_schedule_id' => $fixture['pickup'],
        'number_of_guests' => 2,
        'cash_received' => true,
    ];
}

function stageEightBooking(
    array $fixture,
    string $token
): int {
    $guest = stageEightAccount('User');

    return DB::table('bookings')->insertGetId([
        'user_id' => $guest->id,
        'tour_date_id' => $fixture['date'],
        'pickup_schedule_id' => $fixture['pickup'],
        'number_of_guests' => 2,
        'phone_number' => '09123456789',
        'nationality' => 'Filipino',
        'booking_status' => 'confirmed',
        'qr_token' => $token,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/**
 * Count bookings for this particular test's tour dates.
 *
 * This is safer than counting the entire bookings table,
 * which may already contain unrelated records.
 *
 * @param array<int, int> $dateIds
 */
function stageEightCountBookingsForDates(array $dateIds): int
{
    return DB::table('bookings')
        ->whereIn('tour_date_id', $dateIds)
        ->count();
}

test('operator can open the own walk in form and list own dates', function () {
    $a = stageEightOperator('A');
    $b = stageEightOperator('B');

    $own = stageEightFixture($a);
    $foreign = stageEightFixture($b);

    $this->actingAs(stageEightAccount('Admin', $a))
        ->get(route('operator.walk-ins.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('operator/WalkIn')
            ->has('packages', 1));

    $this->get(
        route('operator.walk-ins.dates', $own['package'])
    )
        ->assertOk()
        ->assertJsonCount(1, 'dates');

    $this->get(
        route('operator.walk-ins.dates', $foreign['package'])
    )->assertNotFound();
});

test('walk in records one confirmed booking and the full cash payment', function () {
    $operator = stageEightOperator('A');
    $fixture = stageEightFixture($operator);

    $this->actingAs(stageEightAccount('Admin', $operator))
        ->post(
            route('operator.walk-ins.store'),
            stageEightWalkInPayload($fixture)
        )
        ->assertRedirect();

    $booking = DB::table('bookings')
        ->where('tour_date_id', $fixture['date'])
        ->first();

    expect($booking)->not->toBeNull();
    expect($booking->booking_status)->toBe('confirmed');
    expect((int) $booking->number_of_guests)->toBe(2);

    $this->assertDatabaseHas('payments', [
        'booking_id' => $booking->id,
        'amount' => 3000,
        'payment_method' => 'Cash',
        'payment_status' => 'paid',
    ]);

    expect(
        DB::table('payments')
            ->where('booking_id', $booking->id)
            ->count()
    )->toBe(1);
});

test('walk in cannot be charged without cash confirmation or placed on foreign tour', function () {
    $a = stageEightOperator('A');
    $b = stageEightOperator('B');

    $own = stageEightFixture($a);
    $foreign = stageEightFixture($b);

    $admin = stageEightAccount('Admin', $a);

    // Both freshly created tour dates must start without bookings.
    $dateIds = [
        $own['date'],
        $foreign['date'],
    ];

    expect(
        stageEightCountBookingsForDates($dateIds)
    )->toBe(0);

    // Test 1: Cash confirmation is required.
    $payload = stageEightWalkInPayload($own);
    $payload['cash_received'] = false;

    $this->actingAs($admin)
        ->post(
            route('operator.walk-ins.store'),
            $payload
        )
        ->assertSessionHasErrors('cash_received');

    // Test 2: Operator A cannot book Operator B's tour.
    $this->post(
        route('operator.walk-ins.store'),
        stageEightWalkInPayload($foreign)
    )->assertNotFound();

    // FIX: Count only bookings linked to these test tour dates.
    // Unrelated existing bookings do not affect the result.
    expect(
        stageEightCountBookingsForDates($dateIds)
    )->toBe(0);

    // Verify the foreign Operator's tour was not modified.
    $this->assertDatabaseHas('tour_dates', [
        'id' => $foreign['date'],
        'package_id' => $foreign['package'],
    ]);
});

test('walk in enforces closures and guest capacity', function () {
    $operator = stageEightOperator('A');
    $fixture = stageEightFixture($operator);

    $admin = stageEightAccount('Admin', $operator);

    // The newly created test tour date must have no bookings.
    expect(
        stageEightCountBookingsForDates([$fixture['date']])
    )->toBe(0);

    // Test 1: Reject guest counts exceeding capacity.
    $payload = stageEightWalkInPayload($fixture);
    $payload['number_of_guests'] = 11;

    $this->actingAs($admin)
        ->post(
            route('operator.walk-ins.store'),
            $payload
        )
        ->assertSessionHasErrors('number_of_guests');

    // Test 2: Close the tour date due to travel restrictions.
    DB::table('tour_dates')
        ->where('id', $fixture['date'])
        ->update([
            'travel_closed' => true,
        ]);

    $this->post(
        route('operator.walk-ins.store'),
        stageEightWalkInPayload($fixture)
    )->assertSessionHasErrors('tour_date_id');

    // FIX: Only check this tour date.
    // No rejected booking should have been inserted.
    expect(
        stageEightCountBookingsForDates([$fixture['date']])
    )->toBe(0);

    // The travel closure must remain in effect.
    $this->assertDatabaseHas('tour_dates', [
        'id' => $fixture['date'],
        'travel_closed' => true,
    ]);
});

test('qr lookup accepts an own token but not another operators token', function () {
    $a = stageEightOperator('A');
    $b = stageEightOperator('B');

    $own = stageEightFixture($a);
    $foreign = stageEightFixture($b);

    $ownId = stageEightBooking(
        $own,
        (string) Str::uuid()
    );

    $foreignToken = (string) Str::uuid();

    $foreignId = stageEightBooking(
        $foreign,
        $foreignToken
    );

    $ownToken = (string) DB::table('bookings')
        ->where('id', $ownId)
        ->value('qr_token');

    $this->actingAs(stageEightAccount('Admin', $a))
        ->post(
            route('operator.qr.verify'),
            ['qr_token' => $ownToken]
        )
        ->assertRedirect(
            route('operator.qr.show', $ownId)
        );

    $this->get(
        route('operator.qr.show', $ownId)
    )
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('operator/QrVerified')
            ->where('booking.id', $ownId));

    $this->post(
        route('operator.qr.verify'),
        ['qr_token' => $foreignToken]
    )->assertSessionHasErrors('qr_token');

    $this->get(
        route('operator.qr.show', $foreignId)
    )->assertNotFound();
});

test('legacy qr and walk in endpoints are denied to operator', function () {
    $operator = stageEightOperator('A');

    $this->actingAs(stageEightAccount('Admin', $operator))
        ->get(route('admin.qrscanner'))
        ->assertForbidden();

    $this->get(
        route('admin.bookings.create')
    )->assertForbidden();

    $this->post(
        route('admin.qrscanner.verify'),
        ['qr_token' => 'invalid']
    )->assertForbidden();

    $this->post(
        route('admin.bookings.store'),
        []
    )->assertForbidden();
});

test('unassigned or inactive admins and tourists cannot access new operator routes', function () {
    $accounts = [
        stageEightAccount('Admin'),
        stageEightAccount(
            'Admin',
            stageEightOperator('Inactive', false)
        ),
        stageEightAccount('User'),
        stageEightAccount('Superadmin'),
    ];

    foreach ($accounts as $account) {
        $this->actingAs($account)
            ->get(route('operator.walk-ins.create'))
            ->assertForbidden();

        $this->get(
            route('operator.qr.index')
        )->assertForbidden();
    }
});
