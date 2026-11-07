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

/**
 * @return array{operator: TourOperator, admin: User, package: int, date: int, pickup: int}
 */
function routedBusiness(string $name): array
{
    $operator = TourOperator::query()->create([
        'name' => $name.' '.Str::random(6),
        'slug' => Str::slug($name).'-'.Str::uuid(),
        'contact_email' => Str::uuid().'@example.com',
        'contact_phone' => '09170000000',
        'is_active' => true,
    ]);

    $admin = User::factory()->create([
        'role_id' => Role::query()->firstOrCreate(['name' => 'Admin'])->id,
        'tour_operator_id' => $operator->id,
    ]);

    $package = DB::table('packages')->insertGetId([
        'tour_operator_id' => $operator->id,
        'package_name' => $name.' Package',
        'boat_name' => $name.' Boat',
        'destination' => 'Basul Island',
        'price' => 1800,
        'status' => 'active',
        'under_maintenance' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $date = DB::table('tour_dates')->insertGetId([
        'package_id' => $package,
        'tour_date' => today()->addDays(12)->toDateString(),
        'capacity' => 8,
        'travel_closed' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $location = DB::table('pickup_locations')->insertGetId([
        'tour_operator_id' => $operator->id,
        'name' => $name.' Pier '.Str::random(4),
        'status' => 'active',
        'latitude' => 9.78,
        'longitude' => 125.49,
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

    return compact('operator', 'admin', 'package', 'date', 'pickup');
}

function routedTourist(): User
{
    return User::factory()->create([
        'role_id' => Role::query()->firstOrCreate(['name' => 'User'])->id,
    ]);
}

function routedPayload(array $business): array
{
    return [
        'tour_date_id' => $business['date'],
        'pickup_schedule_id' => $business['pickup'],
        'pickup_latitude' => 9.78,
        'pickup_longitude' => 125.49,
        'number_of_guests' => 2,
        'phone_number' => '09181234567',
        'nationality' => 'Filipino',
    ];
}

test('a tourist booking is routed only to the operator that owns the package', function () {
    $first = routedBusiness('North Shore');
    $second = routedBusiness('South Shore');
    $tourist = routedTourist();

    $this->actingAs($tourist)
        ->post(route('user.bookings.store'), routedPayload($first))
        ->assertRedirect();

    $booking = Booking::query()->where('user_id', $tourist->id)->first();

    expect($booking)->not->toBeNull()
        ->and($booking->booking_status)->toBe('pending')
        ->and((int) $booking->tourDate->package->tour_operator_id)->toBe($first['operator']->id);

    Notification::assertSentTo($first['admin'], BookingCreatedNotification::class);
    Notification::assertNotSentTo($second['admin'], BookingCreatedNotification::class);

    $this->actingAs($first['admin']);
    expect(Booking::query()->whereKey($booking->id)->exists())->toBeTrue();

    $this->actingAs($second['admin']);
    expect(Booking::query()->whereKey($booking->id)->exists())->toBeFalse();
});

test('tourists cannot book an inactive operator or a business without an admin', function () {
    $inactive = routedBusiness('Closed Coast');
    $inactive['operator']->update([
        'is_active' => false,
        'approval_status' => 'pending',
    ]);
    $closedTourist = routedTourist();

    $this->actingAs($closedTourist)
        ->post(route('user.bookings.store'), routedPayload($inactive))
        ->assertSessionHasErrors('tour_date_id');

    $unassigned = routedBusiness('Open Coast');
    $unassigned['admin']->forceFill(['tour_operator_id' => null])->save();
    $openTourist = routedTourist();

    $this->actingAs($openTourist)
        ->post(route('user.bookings.store'), routedPayload($unassigned))
        ->assertSessionHasErrors('tour_date_id');

    expect(Booking::query()->whereIn('user_id', [$closedTourist->id, $openTourist->id])->count())->toBe(0);

    $this->actingAs($inactive['admin'])
        ->get(route('operator.dashboard'))
        ->assertForbidden();

    $this->actingAs($unassigned['admin'])
        ->get(route('operator.dashboard'))
        ->assertForbidden();
});

test('superadmin can register a business and later link an admin and package without taking another operator records', function () {
    $superadmin = User::factory()->create([
        'role_id' => Role::query()->firstOrCreate(['name' => 'Superadmin'])->id,
    ]);
    $admin = User::factory()->create([
        'role_id' => Role::query()->firstOrCreate(['name' => 'Admin'])->id,
    ]);
    $owned = routedBusiness('Already Owned');

    $loosePackage = DB::table('packages')->insertGetId([
        'tour_operator_id' => null,
        'package_name' => 'Loose Package',
        'boat_name' => 'Loose Boat',
        'destination' => 'Guyam Island',
        'price' => 1200,
        'status' => 'active',
        'under_maintenance' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($superadmin)
        ->post(route('superadmin.operators.store'), [
            'name' => 'New Harbor Tours',
            'contact_email' => 'harbor@example.com',
            'contact_phone' => '09190001111',
            'description' => 'Harbor departures',
            'is_active' => false,
        ])
        ->assertRedirect(route('superadmin.operators.index'));

    $created = TourOperator::query()->where('name', 'New Harbor Tours')->first();

    expect($created)->not->toBeNull()
        ->and($created->is_active)->toBeFalse();

    $this->actingAs($superadmin)
        ->post(route('superadmin.operators.store'), [
            'name' => 'New Harbor Tours',
            'is_active' => true,
        ])
        ->assertSessionHasErrors('name');

    $this->actingAs($superadmin)
        ->patch(route('superadmin.operators.assign', $created), [
            'admin_user_id' => $admin->id,
            'package_ids' => [$loosePackage, $owned['package']],
        ])
        ->assertSessionHasErrors('package_ids');

    expect($admin->fresh()->tour_operator_id)->toBeNull()
        ->and((int) DB::table('packages')->where('id', $owned['package'])->value('tour_operator_id'))
        ->toBe($owned['operator']->id);

    $this->actingAs($superadmin)
        ->patch(route('superadmin.operators.assign', $created), [
            'admin_user_id' => $admin->id,
            'package_ids' => [$loosePackage],
        ])
        ->assertRedirect(route('superadmin.operators.index'));

    expect($admin->fresh()->tour_operator_id)->toBe($created->id)
        ->and((int) DB::table('packages')->where('id', $loosePackage)->value('tour_operator_id'))
        ->toBe($created->id);

    $this->actingAs($superadmin)
        ->get(route('superadmin.operators.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('superadmin/operators/Index')
            ->where('operators', fn ($operators) => collect($operators)->contains(
                fn ($operator) => $operator['name'] === 'New Harbor Tours'
                    && collect($operator['admins'])->contains('name', $admin->name)
            ))
        );
});
