
<?php

use App\Models\Package;
use App\Models\Role;
use App\Models\TourOperator;
use App\Models\User;
use App\Services\TourDateAvailabilityService;
use App\Services\WeatherService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    $this->withoutVite();
});

function stageSixOperator(string $name, bool $active = true): TourOperator
{
    return TourOperator::create([
        'name' => $name,
        'slug' => 'stage-six-'.Str::uuid(),
        'is_active' => $active,
    ]);
}

function stageSixUser(string $roleName, ?TourOperator $operator = null): User
{
    $role = Role::firstOrCreate(['name' => $roleName]);
    $user = User::factory()->create(['role_id' => $role->id]);

    if ($operator !== null) {
        $user->forceFill(['tour_operator_id' => $operator->id])->save();
    }

    return $user->refresh();
}

function stageSixPackage(TourOperator $operator, array $overrides = []): int
{
    return DB::table('packages')->insertGetId(array_merge([
        'tour_operator_id' => $operator->id,
        'package_name' => 'Publish Test Island Tour',
        'boat_name' => 'Test Boat A',
        'destination' => 'Daku Island',
        'price' => 1500,
        'status' => 'inactive',
        'under_maintenance' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides));
}

function stageSixValidSchedule(int $packageId, ?TourOperator $operator = null): int
{
    $dateId = DB::table('tour_dates')->insertGetId([
        'package_id' => $packageId,
        'tour_date' => today()->addDays(9)->toDateString(),
        'capacity' => 15,
        'travel_closed' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('times')->insert([
        'tour_date_id' => $dateId,
        'time' => '08:00:00',
        'description' => 'Departure',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $pickupId = DB::table('pickup_locations')->insertGetId([
        'tour_operator_id' => $operator?->id,
        'name' => 'Stage Six Pickup '.Str::random(6),
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('pickup_schedules')->insert([
        'tour_date_id' => $dateId,
        'pickup_location_id' => $pickupId,
        'pickup_time' => '07:00:00',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $dateId;
}

test('an operator cannot publish a package without a ready schedule', function () {
    $operator = stageSixOperator('A');
    $packageId = stageSixPackage($operator);

    $this->actingAs(stageSixUser('Admin', $operator), 'web')
        ->post(route('operator.packages.publish', $packageId))
        ->assertSessionHasErrors('publication');

    $this->assertDatabaseHas('packages', ['id' => $packageId, 'status' => 'inactive']);
});

test('an operator can publish their own ready package', function () {
    $operator = stageSixOperator('A');
    $packageId = stageSixPackage($operator);
    stageSixValidSchedule($packageId, $operator);

    $this->actingAs(stageSixUser('Admin', $operator), 'web')
        ->post(route('operator.packages.publish', $packageId))
        ->assertRedirect(route('operator.packages.index'));

    $this->assertDatabaseHas('packages', ['id' => $packageId, 'status' => 'active']);
});

test('a package under maintenance cannot be published', function () {
    $operator = stageSixOperator('A');
    $packageId = stageSixPackage($operator, ['under_maintenance' => true]);
    stageSixValidSchedule($packageId, $operator);

    $this->actingAs(stageSixUser('Admin', $operator), 'web')
        ->post(route('operator.packages.publish', $packageId))
        ->assertSessionHasErrors('publication');

    $this->assertDatabaseHas('packages', ['id' => $packageId, 'status' => 'inactive']);
});

test('only the owning operator can change package publication', function () {
    $operatorA = stageSixOperator('A');
    $operatorB = stageSixOperator('B');
    $packageB = stageSixPackage($operatorB);
    stageSixValidSchedule($packageB, $operatorB);

    $this->actingAs(stageSixUser('Admin', $operatorA), 'web')
        ->post(route('operator.packages.publish', $packageB))
        ->assertNotFound();

    $this->post(route('operator.packages.unpublish', $packageB))
        ->assertNotFound();

    $this->assertDatabaseHas('packages', ['id' => $packageB, 'status' => 'inactive']);
});

test('unpublishing stops new bookings without deleting scheduled dates', function () {
    $operator = stageSixOperator('A');
    $packageId = stageSixPackage($operator, ['status' => 'active']);
    $dateId = stageSixValidSchedule($packageId, $operator);

    $this->actingAs(stageSixUser('Admin', $operator), 'web')
        ->post(route('operator.packages.unpublish', $packageId))
        ->assertRedirect(route('operator.packages.index'));

    $this->assertDatabaseHas('packages', ['id' => $packageId, 'status' => 'inactive']);
    $this->assertDatabaseHas('tour_dates', ['id' => $dateId, 'package_id' => $packageId]);
});

test('super admin, tourists, unassigned and inactive admins cannot use operator publication', function () {
    $operator = stageSixOperator('A');
    $packageId = stageSixPackage($operator);

    $accounts = [
        stageSixUser('Superadmin'),
        stageSixUser('User'),
        stageSixUser('Admin'),
        stageSixUser('Admin', stageSixOperator('Inactive', false)),
    ];

    foreach ($accounts as $account) {
        $this->actingAs($account, 'web')
            ->post(route('operator.packages.publish', $packageId))
            ->assertForbidden();
    }
});

test('opening an operator owned package does not manufacture extra dates', function () {
    $operator = stageSixOperator('A');
    $packageId = stageSixPackage($operator);
    stageSixValidSchedule($packageId, $operator);

    $weather = \Mockery::mock(WeatherService::class);
    $weather->shouldReceive('fillMissingForecasts')->once()->andReturn(0);

    $service = new TourDateAvailabilityService($weather);
    $service->ensure(Package::withoutGlobalScopes()->findOrFail($packageId));

    expect(DB::table('tour_dates')->where('package_id', $packageId)->count())->toBe(1);
});
