<?php

use App\Models\Role;
use App\Models\TourOperator;
use App\Models\User;
use App\Services\RollingTourScheduleService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(DatabaseTransactions::class);

function rollingOperator(string $name): TourOperator
{
    $operator = TourOperator::query()->create([
        'name' => $name,
        'slug' => 'rolling-'.Str::uuid(),
        'is_active' => true,
        'approval_status' => TourOperator::STATUS_ACTIVE,
    ]);

    $role = Role::query()->firstOrCreate(['name' => 'Admin']);
    $admin = User::factory()->create(['role_id' => $role->id]);
    $admin->forceFill(['tour_operator_id' => $operator->id])->save();

    return $operator->fresh();
}

function rollingPackage(TourOperator $operator, string $boat, int $capacity): int
{
    $packageId = (int) DB::table('packages')->insertGetId([
        'tour_operator_id' => $operator->id,
        'package_name' => 'Rolling Tour '.$boat,
        'boat_name' => $boat,
        'destination' => 'Siargao',
        'price' => 1800,
        'status' => 'active',
        'under_maintenance' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $pickupId = (int) DB::table('pickup_locations')->insertGetId([
        'tour_operator_id' => $operator->id,
        'name' => 'Hub '.$boat,
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $tourDateId = (int) DB::table('tour_dates')->insertGetId([
        'package_id' => $packageId,
        'tour_date' => today()->toDateString(),
        'capacity' => $capacity,
        'travel_closed' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('times')->insert([
        'tour_date_id' => $tourDateId,
        'time' => '08:00:00',
        'description' => 'Departure',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('pickup_schedules')->insert([
        'tour_date_id' => $tourDateId,
        'pickup_location_id' => $pickupId,
        'pickup_time' => '07:00:00',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $packageId;
}

test('dry-run reports missing daily schedules without writing', function (): void {
    $operator = rollingOperator('Rolling Dry');
    $packageId = rollingPackage($operator, 'Dry Boat', 20);

    $this->artisan('tours:ensure-rolling-schedules', [
        '--days' => 10,
        '--package' => $packageId,
        '--dry-run' => true,
    ])->assertSuccessful();

    $count = DB::table('tour_dates')->where('package_id', $packageId)->count();

    expect($count)->toBe(1);
});

test('command creates missing daily schedules idempotently and preserves capacity', function (): void {
    $operator = rollingOperator('Rolling Write');
    $packageId = rollingPackage($operator, 'Write Boat', 50);

    $this->artisan('tours:ensure-rolling-schedules', [
        '--days' => 7,
        '--package' => $packageId,
    ])->assertSuccessful();

    $dates = DB::table('tour_dates')
        ->where('package_id', $packageId)
        ->whereDate('tour_date', '>=', today()->toDateString())
        ->orderBy('tour_date')
        ->get();

    expect($dates)->toHaveCount(7)
        ->and($dates->every(fn ($row): bool => (int) $row->capacity === 50))->toBeTrue();

    foreach ($dates as $date) {
        expect(DB::table('times')->where('tour_date_id', $date->id)->count())->toBeGreaterThan(0)
            ->and(DB::table('pickup_schedules')->where('tour_date_id', $date->id)->count())->toBeGreaterThan(0);
    }

    $this->artisan('tours:ensure-rolling-schedules', [
        '--days' => 7,
        '--package' => $packageId,
    ])->assertSuccessful();

    expect(DB::table('tour_dates')->where('package_id', $packageId)->count())->toBe(7);
});

test('boat conflicts skip overlapping days for the same vessel', function (): void {
    $operator = rollingOperator('Rolling Conflict');
    $first = rollingPackage($operator, 'Shared Boat', 20);
    $second = rollingPackage($operator, 'Shared Boat', 20);

    // Move second package template off the first three days so those days are missing.
    DB::table('tour_dates')
        ->where('package_id', $second)
        ->update(['tour_date' => today()->addDays(10)->toDateString()]);

    $service = app(RollingTourScheduleService::class);
    $report = $service->ensure(days: 3, dryRun: false, packageId: $second);
    $row = collect($report)->firstWhere('package_id', $second);

    // Day 0 conflicts with $first; days 1-2 can be created.
    expect($row['skipped_boat_conflict'])->toBe(1)
        ->and($row['create_dates'])->toBe(2);
});

test('capacity above advisory limit is flagged but preserved', function (): void {
    $operator = rollingOperator('Rolling Cap Flag');
    $packageId = rollingPackage($operator, 'Big Boat', 100);

    $report = app(RollingTourScheduleService::class)->ensure(
        days: 5,
        dryRun: true,
        packageId: $packageId,
    );

    expect($report[0]['capacity'])->toBe(100)
        ->and($report[0]['capacity_flag'])->toContain('exceeds advisory vessel limit');
});
