<?php

use App\Models\Booking;
use App\Models\Role;
use App\Models\TourOperator;
use App\Models\User;
use App\Notifications\BookingCreatedNotification;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    $this->withoutVite();
    config()->set('inertia.ssr.enabled', false);
    Notification::fake();
});

function assignmentOperator(string $name, bool $active = true): array
{
    $operator = TourOperator::query()->create([
        'name' => $name.' '.Str::random(5),
        'slug' => Str::slug($name).'-'.Str::uuid(),
        'is_active' => $active,
    ]);

    $admin = User::factory()->create([
        'name' => $name.' Admin',
        'role_id' => Role::query()->firstOrCreate(['name' => 'Admin'])->id,
        'tour_operator_id' => $operator->id,
    ]);

    return compact('operator', 'admin');
}

function assignmentPackage(string $name): int
{
    return DB::table('packages')->insertGetId([
        'tour_operator_id' => null,
        'package_name' => $name.' '.Str::random(4),
        'boat_name' => $name.' Boat',
        'destination' => 'Guyam Island',
        'price' => 1750,
        'status' => 'active',
        'under_maintenance' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function assignmentSchedule(int $packageId, ?int $operatorId): array
{
    $date = DB::table('tour_dates')->insertGetId([
        'package_id' => $packageId,
        'tour_date' => today()->addDays(9)->toDateString(),
        'capacity' => 6,
        'travel_closed' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $location = DB::table('pickup_locations')->insertGetId([
        'tour_operator_id' => $operatorId,
        'name' => 'Assignment Pier '.Str::random(4),
        'status' => 'active',
        'latitude' => 9.78,
        'longitude' => 125.49,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $pickup = DB::table('pickup_schedules')->insertGetId([
        'tour_date_id' => $date,
        'pickup_location_id' => $location,
        'pickup_time' => '08:00:00',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return compact('date', 'pickup');
}

test('superadmin sees unassigned packages and can assign several to one active business', function () {
    $first = assignmentOperator('Harbor');
    $second = assignmentOperator('Cove');
    $packageA = assignmentPackage('Harbor Hop');
    $packageB = assignmentPackage('Harbor Cove');
    $before = TourOperator::query()->count();
    $superadmin = User::factory()->create([
        'role_id' => Role::query()->firstOrCreate(['name' => 'Superadmin'])->id,
    ]);

    $this->actingAs($superadmin)
        ->get(route('superadmin.operators.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('superadmin/operators/Index')
            ->where('packageAssignments', fn ($rows) => collect($rows)->contains(
                fn ($row) => $row['id'] === $packageA
                    && $row['assignment_status'] === 'Unassigned'
                    && $row['operator_name'] === null
                    && $row['admin_name'] === null
            ))
        );

    $this->actingAs($superadmin)
        ->patch(route('superadmin.package-assignments.update'), [
            'tour_operator_id' => $first['operator']->id,
            'package_ids' => [$packageA, $packageB],
        ])
        ->assertRedirect(route('superadmin.operators.index'));

    expect(TourOperator::query()->count())->toBe($before)
        ->and((int) DB::table('packages')->where('id', $packageA)->value('tour_operator_id'))->toBe($first['operator']->id)
        ->and((int) DB::table('packages')->where('id', $packageB)->value('tour_operator_id'))->toBe($first['operator']->id)
        ->and((string) DB::table('packages')->where('id', $packageA)->value('price'))->toBe('1750.00')
        ->and((int) DB::table('packages')->where('id', $packageA)->value('tour_operator_id'))->not->toBe($second['operator']->id);

    $this->actingAs($superadmin)
        ->patch(route('superadmin.package-assignments.update'), [
            'tour_operator_id' => $second['operator']->id,
            'package_ids' => [$packageB],
        ])
        ->assertRedirect(route('superadmin.operators.index'));

    expect((int) DB::table('packages')->where('id', $packageA)->value('tour_operator_id'))->toBe($first['operator']->id)
        ->and((int) DB::table('packages')->where('id', $packageB)->value('tour_operator_id'))->toBe($second['operator']->id);
});

test('a new booking follows the assigned package and stays hidden from the other operator', function () {
    $first = assignmentOperator('North');
    $second = assignmentOperator('South');
    $packageId = assignmentPackage('North Route');
    $superadmin = User::factory()->create([
        'role_id' => Role::query()->firstOrCreate(['name' => 'Superadmin'])->id,
    ]);

    $this->actingAs($superadmin)
        ->patch(route('superadmin.package-assignments.update'), [
            'tour_operator_id' => $first['operator']->id,
            'package_ids' => [$packageId],
        ])
        ->assertRedirect();

    $schedule = assignmentSchedule($packageId, $first['operator']->id);
    $tourist = User::factory()->create([
        'role_id' => Role::query()->firstOrCreate(['name' => 'User'])->id,
    ]);

    $this->actingAs($tourist)
        ->post(route('user.bookings.store'), [
            'tour_date_id' => $schedule['date'],
            'pickup_schedule_id' => $schedule['pickup'],
            'pickup_latitude' => 9.78,
            'pickup_longitude' => 125.49,
            'number_of_guests' => 2,
            'phone_number' => '09181234567',
            'nationality' => 'Filipino',
        ])
        ->assertRedirect();

    $booking = Booking::query()->where('user_id', $tourist->id)->first();

    expect($booking)->not->toBeNull()
        ->and($booking->booking_status)->toBe('pending')
        ->and((int) $booking->tourDate->package->tour_operator_id)->toBe($first['operator']->id);

    Notification::assertSentTo($first['admin'], BookingCreatedNotification::class);
    Notification::assertNotSentTo($second['admin'], BookingCreatedNotification::class);

    $this->actingAs($first['admin']);
    expect(Booking::query()->whereKey($booking->id)->exists())->toBeTrue();
    expect(DB::table('packages')->where('id', $packageId)->where('tour_operator_id', $first['operator']->id)->exists())->toBeTrue();

    $this->actingAs($second['admin']);
    expect(Booking::query()->whereKey($booking->id)->exists())->toBeFalse();
});

test('tourists cannot book an unassigned package and inactive businesses cannot receive packages', function () {
    $packageId = assignmentPackage('Open Water');
    $schedule = assignmentSchedule($packageId, null);

    $tourist = User::factory()->create([
        'role_id' => Role::query()->firstOrCreate(['name' => 'User'])->id,
    ]);

    $this->actingAs($tourist)
        ->post(route('user.bookings.store'), [
            'tour_date_id' => $schedule['date'],
            'pickup_schedule_id' => $schedule['pickup'],
            'pickup_latitude' => 9.78,
            'pickup_longitude' => 125.49,
            'number_of_guests' => 1,
            'phone_number' => '09180001111',
            'nationality' => 'Filipino',
        ])
        ->assertSessionHasErrors('tour_date_id');

    expect(Booking::query()->where('user_id', $tourist->id)->count())->toBe(0);

    $inactive = assignmentOperator('Closed', false);
    $superadmin = User::factory()->create([
        'role_id' => Role::query()->firstOrCreate(['name' => 'Superadmin'])->id,
    ]);

    $this->actingAs($superadmin)
        ->patch(route('superadmin.package-assignments.update'), [
            'tour_operator_id' => $inactive['operator']->id,
            'package_ids' => [$packageId],
        ])
        ->assertSessionHasErrors('tour_operator_id');

    expect(DB::table('packages')->where('id', $packageId)->value('tour_operator_id'))->toBeNull();
});
