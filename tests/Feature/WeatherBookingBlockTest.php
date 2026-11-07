<?php

use App\Models\Booking;
use App\Models\PickupSchedule;
use App\Models\Role;
use App\Models\User;
use App\Models\WeatherForecast;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

function openSchedule(): PickupSchedule
{
    $schedule = PickupSchedule::query()
        ->whereHas('tourDate', function ($query) {
            $query->whereDate('tour_date', '>=', now()->toDateString())
                ->whereHas('package', fn ($package) => $package
                    ->where('status', 'active')
                    ->where('under_maintenance', false));
        })
        ->with('tourDate')
        ->get()
        ->first(function (PickupSchedule $schedule) {
            $booked = Booking::query()
                ->where('tour_date_id', $schedule->tour_date_id)
                ->whereIn('booking_status', ['pending', 'confirmed'])
                ->sum('number_of_guests');

            return ($schedule->tourDate->capacity - $booked) >= 1;
        });

    expect($schedule)->not->toBeNull();

    return $schedule;
}

function forecast(PickupSchedule $schedule, string $level): void
{
    WeatherForecast::query()->updateOrCreate(
        ['tour_date_id' => $schedule->tour_date_id],
        [
            'forecast_date' => $schedule->tourDate->tour_date->toDateString(),
            'advisory_level' => $level,
            'advisory_badge' => $level,
            'weather_condition' => 'Test weather',
        ],
    );
}

test('a tourist cannot book a date with dangerous weather', function () {
    $touristRole = Role::query()->where('name', 'User')->first();
    $tourist = User::factory()->create(['role_id' => $touristRole->id]);
    $schedule = openSchedule();
    $schedule->tourDate->update(['travel_closed' => false]);
    forecast($schedule, 'red');

    $this->actingAs($tourist)
        ->withSession(['_token' => 'test-token'])
        ->from(route('user.packages.index'))
        ->post(route('user.bookings.store'), [
            '_token' => 'test-token',
            'tour_date_id' => $schedule->tour_date_id,
            'pickup_schedule_id' => $schedule->id,
            'pickup_latitude' => 9.78,
            'pickup_longitude' => 125.49,
            'number_of_guests' => 1,
            'phone_number' => '09175550301',
            'nationality' => 'Filipino',
        ])
        ->assertRedirect(route('user.packages.index'))
        ->assertSessionHasErrors('tour_date_id');

    expect(Booking::query()->where('phone_number', '09175550301')->exists())->toBeFalse();
});

test('strong wind closes booking and a caution advisory stays open', function () {
    $touristRole = Role::query()->where('name', 'User')->first();
    $tourist = User::factory()->create(['role_id' => $touristRole->id]);
    $schedule = openSchedule();
    $schedule->tourDate->update(['travel_closed' => false]);
    forecast($schedule, 'orange');

    $this->actingAs($tourist)
        ->withSession(['_token' => 'test-token'])
        ->from(route('user.packages.index'))
        ->post(route('user.bookings.store'), [
            '_token' => 'test-token',
            'tour_date_id' => $schedule->tour_date_id,
            'pickup_schedule_id' => $schedule->id,
            'pickup_latitude' => 9.78,
            'pickup_longitude' => 125.49,
            'number_of_guests' => 1,
            'phone_number' => '09175550302',
            'nationality' => 'Filipino',
        ])
        ->assertSessionHasErrors('tour_date_id');

    forecast($schedule, 'yellow');

    $this->actingAs($tourist)
        ->withSession(['_token' => 'test-token'])
        ->post(route('user.bookings.store'), [
            '_token' => 'test-token',
            'tour_date_id' => $schedule->tour_date_id,
            'pickup_schedule_id' => $schedule->id,
            'pickup_latitude' => 9.78,
            'pickup_longitude' => 125.49,
            'number_of_guests' => 1,
            'phone_number' => '09175550303',
            'nationality' => 'Filipino',
        ])
        ->assertSessionDoesntHaveErrors('tour_date_id');

    expect(Booking::query()->where('phone_number', '09175550303')->exists())->toBeTrue();
});

test('staff can close a date even when the forecast is calm', function () {
    $adminRole = Role::query()->where('name', 'Superadmin')->first();
    $admin = User::factory()->create(['role_id' => $adminRole->id]);
    $schedule = openSchedule();
    forecast($schedule, 'green');
    $schedule->tourDate->update(['travel_closed' => false]);

    $this->actingAs($admin)
        ->withSession(['_token' => 'test-token'])
        ->post(route('admin.weather-advisories.travel', $schedule->tourDate), [
            '_token' => 'test-token',
        ])
        ->assertRedirect();

    expect($schedule->tourDate->fresh()->travel_closed)->toBeTrue();

    $touristRole = Role::query()->where('name', 'User')->first();
    $tourist = User::factory()->create(['role_id' => $touristRole->id]);

    $this->actingAs($tourist)
        ->withSession(['_token' => 'test-token'])
        ->from(route('user.packages.index'))
        ->post(route('user.bookings.store'), [
            '_token' => 'test-token',
            'tour_date_id' => $schedule->tour_date_id,
            'pickup_schedule_id' => $schedule->id,
            'pickup_latitude' => 9.78,
            'pickup_longitude' => 125.49,
            'number_of_guests' => 1,
            'phone_number' => '09175550304',
            'nationality' => 'Filipino',
        ])
        ->assertSessionHasErrors('tour_date_id');
});

test('walk-in booking is also blocked when the wind is dangerous', function () {
    $adminRole = Role::query()->where('name', 'Superadmin')->first();
    $admin = User::factory()->create(['role_id' => $adminRole->id]);
    $schedule = openSchedule();
    $schedule->tourDate->update(['travel_closed' => false]);
    forecast($schedule, 'red');

    $this->actingAs($admin)
        ->withSession(['_token' => 'test-token'])
        ->from(route('admin.bookings.create'))
        ->post(route('admin.bookings.store'), [
            '_token' => 'test-token',
            'customer_name' => 'Closed Weather Guest',
            'phone_number' => '09175550305',
            'nationality' => 'Filipino',
            'tour_date_id' => $schedule->tour_date_id,
            'pickup_schedule_id' => $schedule->id,
            'number_of_guests' => 1,
        ])
        ->assertSessionHasErrors('tour_date_id');

    expect(Booking::query()->where('phone_number', '09175550305')->exists())->toBeFalse();
});
