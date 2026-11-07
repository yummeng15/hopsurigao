
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
    // Backend tests should not require built frontend files.
    $this->withoutVite();
});

function stageFiveOperator(string $name): TourOperator
{
    return TourOperator::create([
        'name' => $name,
        'slug' => 'stage-five-'.Str::uuid(),
        'is_active' => true,
    ]);
}

function stageFiveAdmin(TourOperator $operator): User
{
    $role = Role::firstOrCreate(['name' => 'Admin']);
    $user = User::factory()->create(['role_id' => $role->id]);

    $user->forceFill([
        'tour_operator_id' => $operator->id,
    ])->save();

    return $user->refresh();
}

function stageFivePackage(TourOperator $operator): int
{
    return DB::table('packages')->insertGetId([
        'tour_operator_id' => $operator->id,
        'package_name' => 'Scheduling Test Tour',
        'boat_name' => 'Test Boat',
        'destination' => 'Daku Island',
        'price' => 1500,
        'status' => 'inactive',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function stageFivePickup(?TourOperator $operator = null): int
{
    return DB::table('pickup_locations')->insertGetId([
        'tour_operator_id' => $operator?->id,
        'name' => 'Testing Pickup '.Str::random(8),
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function stageFivePayload(int $pickupId): array
{
    return [
        'tour_date' => today()->addDays(12)->format('Y-m-d'),
        'capacity' => 12,
        'times' => [
            [
                'time' => '08:30',
                'description' => 'Departure',
            ],
        ],
        'pickup_schedules' => [
            [
                'pickup_location_id' => $pickupId,
                'pickup_time' => '07:30',
            ],
        ],
    ];
}

test('operator can view own schedule page', function () {
    $operator = stageFiveOperator('A');
    $package = stageFivePackage($operator);

    $this->actingAs(stageFiveAdmin($operator))
        ->get(route('operator.schedules.index', $package))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('operator/Schedules')
            ->where('package.id', $package)
            ->has('dates')
            ->has('locations')
            ->has('today'));
});

test('operator can create a date with times and an allowed pickup', function () {
    $operator = stageFiveOperator('A');
    $package = stageFivePackage($operator);
    $pickup = stageFivePickup();

    $this->actingAs(stageFiveAdmin($operator))
        ->post(
            route('operator.schedules.store', $package),
            stageFivePayload($pickup)
        )
        ->assertRedirect(route('operator.schedules.index', $package));

    $date = DB::table('tour_dates')
        ->where('package_id', $package)
        ->first();

    expect($date)->not->toBeNull();
    expect((int) $date->capacity)->toBe(12);

    $this->assertDatabaseHas('times', [
        'tour_date_id' => $date->id,
        'description' => 'Departure',
    ]);

    $this->assertDatabaseHas('pickup_schedules', [
        'tour_date_id' => $date->id,
        'pickup_location_id' => $pickup,
    ]);
});

test('cannot create duplicate tour date', function () {
    $operator = stageFiveOperator('A');
    $package = stageFivePackage($operator);
    $pickup = stageFivePickup();
    $payload = stageFivePayload($pickup);

    DB::table('tour_dates')->insert([
        'package_id' => $package,
        'tour_date' => $payload['tour_date'],
        'capacity' => 10,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs(stageFiveAdmin($operator))
        ->post(route('operator.schedules.store', $package), $payload)
        ->assertSessionHasErrors('tour_date');
});

test('cannot use another operators pickup or schedule', function () {
    $operatorA = stageFiveOperator('A');
    $operatorB = stageFiveOperator('B');

    $packageA = stageFivePackage($operatorA);
    $packageB = stageFivePackage($operatorB);

    $foreignPickup = stageFivePickup($operatorB);
    $payload = stageFivePayload($foreignPickup);

    $this->actingAs(stageFiveAdmin($operatorA))
        ->post(route('operator.schedules.store', $packageA), $payload)
        ->assertSessionHasErrors('pickup_schedules.0.pickup_location_id');

    $this->get(route('operator.schedules.index', $packageB))
        ->assertNotFound();

    $this->post(route('operator.schedules.store', $packageB), $payload)
        ->assertNotFound();
});

test('booked date allows capacity change but rejects time changes and over-reduction', function () {
    $operator = stageFiveOperator('A');
    $package = stageFivePackage($operator);
    $pickup = stageFivePickup();

    $dateId = DB::table('tour_dates')->insertGetId([
        'package_id' => $package,
        'tour_date' => today()->addDays(12)->format('Y-m-d'),
        'capacity' => 12,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $pickupScheduleId = DB::table('pickup_schedules')->insertGetId([
        'tour_date_id' => $dateId,
        'pickup_location_id' => $pickup,
        'pickup_time' => '07:30',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $touristRole = Role::firstOrCreate(['name' => 'User']);
    $tourist = User::factory()->create(['role_id' => $touristRole->id]);

    DB::table('bookings')->insert([
        'user_id' => $tourist->id,
        'tour_date_id' => $dateId,
        'pickup_schedule_id' => $pickupScheduleId,
        'number_of_guests' => 8,
        'phone_number' => '09123456789',
        'nationality' => 'Filipino',
        'booking_status' => 'confirmed',
        'qr_token' => (string) Str::uuid(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs(stageFiveAdmin($operator))
        ->put(route('operator.schedules.update', [$package, $dateId]), [
            'capacity' => 7,
        ])
        ->assertSessionHasErrors('capacity');

    $this->put(route('operator.schedules.update', [$package, $dateId]), [
        'capacity' => 15,
        'times' => [
            [
                'time' => '09:00',
                'description' => 'Changed',
            ],
        ],
    ])->assertSessionHasErrors('capacity');

    $this->put(route('operator.schedules.update', [$package, $dateId]), [
        'capacity' => 15,
    ])->assertRedirect(route('operator.schedules.index', $package));

    $this->assertDatabaseHas('tour_dates', [
        'id' => $dateId,
        'capacity' => 15,
    ]);
});
