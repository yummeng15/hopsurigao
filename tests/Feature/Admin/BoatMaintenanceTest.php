<?php

use App\Models\Booking;
use App\Models\Package;
use App\Models\PickupSchedule;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

test('the operator can mark a boat under maintenance and booking is closed', function () {
    $operatorRole = Role::query()->where('name', 'Admin')->first();
    $touristRole = Role::query()->where('name', 'User')->first();

    $operator = User::factory()->create([
        'role_id' => $operatorRole->id,
    ]);

    $tourist = User::factory()->create([
        'role_id' => $touristRole->id,
    ]);

    $schedule = PickupSchedule::query()
        ->whereHas('tourDate', function ($query) {
            $query->whereDate('tour_date', '>=', now()->toDateString())
                ->whereHas('package', fn ($package) => $package->where('status', 'active'));
        })
        ->with('tourDate.package')
        ->get()
        ->first(function (PickupSchedule $schedule) {
            $booked = Booking::query()
                ->where('tour_date_id', $schedule->tour_date_id)
                ->whereIn('booking_status', ['pending', 'confirmed'])
                ->sum('number_of_guests');

            return ($schedule->tourDate->capacity - $booked) >= 1;
        });

    expect($schedule)->not->toBeNull();

    $package = $schedule->tourDate->package;

    $this->actingAs($operator)
        ->get(route('admin.boat-maintenance.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/boats/Index')
            ->has('boats')
            ->missing('packages')
            ->where('boats', function ($boats) use ($package) {
                $row = collect($boats)->firstWhere('id', $package->id);

                return is_array($row)
                    && filled($row['boat_name'])
                    && ! array_key_exists('package_name', $row);
            }));

    $this->actingAs($operator)
        ->withSession(['_token' => 'test-token'])
        ->put(route('admin.boat-maintenance.update', $package), [
            '_token' => 'test-token',
            'boat_name' => $package->boat_name,
            'under_maintenance' => true,
        ])
        ->assertRedirect();

    expect($package->fresh()->under_maintenance)->toBeTrue();

    $this->actingAs($tourist)
        ->withSession(['_token' => 'test-token'])
        ->from(route('user.bookings.create', ['tour_date' => $schedule->tour_date_id]))
        ->post(route('user.bookings.store'), [
            '_token' => 'test-token',
            'tour_date_id' => $schedule->tour_date_id,
            'pickup_schedule_id' => $schedule->id,
            'pickup_latitude' => 9.78,
            'pickup_longitude' => 125.5,
            'number_of_guests' => 1,
            'phone_number' => '09175550301',
            'nationality' => 'Filipino',
        ])
        ->assertSessionHasErrors('tour_date_id');

    $this->actingAs($operator)
        ->withSession(['_token' => 'test-token'])
        ->from(route('admin.bookings.create'))
        ->post(route('admin.bookings.store'), [
            '_token' => 'test-token',
            'customer_name' => 'Walk In Guest',
            'phone_number' => '09175550302',
            'nationality' => 'Filipino',
            'tour_date_id' => $schedule->tour_date_id,
            'pickup_schedule_id' => $schedule->id,
            'number_of_guests' => 1,
        ])
        ->assertSessionHasErrors('tour_date_id');

    expect(Booking::query()->where('phone_number', '09175550301')->exists())->toBeFalse()
        ->and(Booking::query()->where('phone_number', '09175550302')->exists())->toBeFalse();
});

test('staff can rename a boat without using the package title', function () {
    $operatorRole = Role::query()->where('name', 'Admin')->first();

    $operator = User::factory()->create([
        'role_id' => $operatorRole->id,
    ]);

    $package = Package::query()->where('status', 'active')->first();

    expect($package)->not->toBeNull();

    $renamed = 'Harbor Bangka '.uniqid();

    $this->actingAs($operator)
        ->withSession(['_token' => 'test-token'])
        ->put(route('admin.boat-maintenance.update', $package), [
            '_token' => 'test-token',
            'boat_name' => $renamed,
            'under_maintenance' => (bool) $package->under_maintenance,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($package->fresh()->boat_name)->toBe($renamed)
        ->and($package->fresh()->boat_name)->not->toBe($package->package_name);
});

test('a tourist cannot open boat maintenance', function () {
    $touristRole = Role::query()->where('name', 'User')->first();
    $tourist = User::factory()->create([
        'role_id' => $touristRole->id,
    ]);

    $this->actingAs($tourist)
        ->get(route('admin.boat-maintenance.index'))
        ->assertForbidden();
});
