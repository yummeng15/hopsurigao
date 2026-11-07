
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

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    $this->withoutVite();
    Notification::fake();
});

/** @return array{operator: TourOperator, tourist: User, package: int, date: int, pickup: int, location: int} */
function availabilityFixture(int $capacity = 4): array
{
    $operator = TourOperator::query()->create([
        'name' => 'Availability '.Str::random(9),
        'slug' => 'availability-'.Str::uuid(),
        'is_active' => true,
    ]);

    User::factory()->create([
        'role_id' => Role::query()->firstOrCreate(['name' => 'Admin'])->id,
        'tour_operator_id' => $operator->id,
    ]);

    $package = DB::table('packages')->insertGetId([
        'tour_operator_id' => $operator->id,
        'package_name' => 'Availability Island Tour',
        'boat_name' => 'Availability Boat',
        'destination' => 'Daku Island',
        'price' => 1500,
        'status' => 'active',
        'under_maintenance' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $date = DB::table('tour_dates')->insertGetId([
        'package_id' => $package,
        'tour_date' => today()->addDays(10)->toDateString(),
        'capacity' => $capacity,
        'travel_closed' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $location = DB::table('pickup_locations')->insertGetId([
        'tour_operator_id' => $operator->id,
        'name' => 'Availability Pier '.Str::random(10),
        'status' => 'active',
        'latitude' => 9.86,
        'longitude' => 126.08,
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

    $role = Role::query()->firstOrCreate(['name' => 'User']);
    $tourist = User::factory()->create(['role_id' => $role->id]);

    return compact('operator', 'tourist', 'package', 'date', 'pickup', 'location');
}

function availabilityPayload(array $fixture): array
{
    return [
        'tour_date_id' => $fixture['date'],
        'pickup_schedule_id' => $fixture['pickup'],
        'pickup_latitude' => 9.86,
        'pickup_longitude' => 126.08,
        'number_of_guests' => 2,
        'phone_number' => '09123456789',
        'nationality' => 'Filipino',
        'special_request' => null,
    ];
}

function availabilityBooking(array $fixture, User $user): Booking
{
    return Booking::query()->create([
        'user_id' => $user->id,
        'tour_date_id' => $fixture['date'],
        'pickup_schedule_id' => $fixture['pickup'],
        'pickup_latitude' => 9.86,
        'pickup_longitude' => 126.08,
        'number_of_guests' => 2,
        'phone_number' => '09123456789',
        'nationality' => 'Filipino',
        'booking_status' => 'pending',
    ]);
}

test('available tour saves the agreed price and redirects to GCash payment', function () {
    $f = availabilityFixture();

    $this->actingAs($f['tourist'])
        ->post(route('user.bookings.store'), availabilityPayload($f))
        ->assertRedirect();

    $booking = Booking::query()
        ->where('user_id', $f['tourist']->id)
        ->where('tour_date_id', $f['date'])
        ->firstOrFail();

    expect($booking->booking_status)->toBe('pending')
        ->and($booking->number_of_guests)->toBe(2)
        ->and((float) $booking->booked_unit_price)->toBe(1500.0)
        ->and((float) $booking->booked_total)->toBe(3000.0)
        ->and($booking->price_snapshot_source)->toBe('at_booking');

    // Editing the catalog price later does not reprice an existing booking.
    DB::table('packages')->where('id', $f['package'])->update(['price' => 2500]);
    expect((float) $booking->fresh()->booked_total)->toBe(3000.0);
});

test('inactive packages or boats under maintenance reject new online reservations', function () {
    foreach (['inactive', 'maintenance'] as $mode) {
        $f = availabilityFixture();

        DB::table('packages')->where('id', $f['package'])->update(
            $mode === 'inactive'
                ? ['status' => 'inactive']
                : ['under_maintenance' => true]
        );

        $this->actingAs($f['tourist'])
            ->post(route('user.bookings.store'), availabilityPayload($f))
            ->assertSessionHasErrors('tour_date_id');

        expect(Booking::query()->where('user_id', $f['tourist']->id)->count())->toBe(0);
    }
});

test('a manually closed date or dangerous weather blocks new online reservations', function () {
    $closed = availabilityFixture();
    DB::table('tour_dates')->where('id', $closed['date'])->update(['travel_closed' => true]);

    $this->actingAs($closed['tourist'])
        ->post(route('user.bookings.store'), availabilityPayload($closed))
        ->assertSessionHasErrors('tour_date_id');

    foreach (['orange', 'red'] as $level) {
        $f = availabilityFixture();

        DB::table('weather_forecasts')->insert([
            'tour_date_id' => $f['date'],
            'forecast_date' => today()->addDays(10)->toDateString(),
            'advisory_level' => $level,
            'advisory_badge' => ucfirst($level),
            'weather_condition' => 'Unsafe weather',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($f['tourist'])
            ->post(route('user.bookings.store'), availabilityPayload($f))
            ->assertSessionHasErrors('tour_date_id');

        expect(Booking::query()->where('user_id', $f['tourist']->id)->count())->toBe(0);
    }
});

test('disabled pickup point or another operators private pickup is rejected', function () {
    $f = availabilityFixture();

    DB::table('pickup_locations')->where('id', $f['location'])->update([
        'status' => 'inactive',
    ]);

    $this->actingAs($f['tourist'])
        ->post(route('user.bookings.store'), availabilityPayload($f))
        ->assertSessionHasErrors('pickup_schedule_id');

    $own = availabilityFixture();
    $other = availabilityFixture();

    // Schedule on our selected date, but pickup belongs to a different operator.
    $foreignPickup = DB::table('pickup_schedules')->insertGetId([
        'tour_date_id' => $own['date'],
        'pickup_location_id' => $other['location'],
        'pickup_time' => '08:00:00',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $payload = availabilityPayload($own);
    $payload['pickup_schedule_id'] = $foreignPickup;

    $this->actingAs($own['tourist'])
        ->post(route('user.bookings.store'), $payload)
        ->assertSessionHasErrors('pickup_schedule_id');
});

test('full capacity rejects another booking and does not oversell seats', function () {
    $f = availabilityFixture(2);

    $other = User::factory()->create([
        'role_id' => $f['tourist']->role_id,
    ]);

    availabilityBooking($f, $other);

    $this->actingAs($f['tourist'])
        ->post(route('user.bookings.store'), availabilityPayload($f))
        ->assertSessionHasErrors('number_of_guests');

    expect(
        (int) Booking::query()
            ->where('tour_date_id', $f['date'])
            ->whereIn('booking_status', ['pending', 'confirmed'])
            ->sum('number_of_guests')
    )->toBe(2);
});

test('duplicate active reservation for the same tourist and date is rejected', function () {
    $f = availabilityFixture();

    availabilityBooking($f, $f['tourist']);

    $this->actingAs($f['tourist'])
        ->post(route('user.bookings.store'), availabilityPayload($f))
        ->assertSessionHasErrors('tour_date_id');

    expect(
        Booking::query()
            ->where('user_id', $f['tourist']->id)
            ->where('tour_date_id', $f['date'])
            ->count()
    )->toBe(1);
});

test('past dates and inactive operators are rejected at final submission', function () {
    $past = availabilityFixture();

    DB::table('tour_dates')->where('id', $past['date'])->update([
        'tour_date' => today()->subDay()->toDateString(),
    ]);

    $this->actingAs($past['tourist'])
        ->post(route('user.bookings.store'), availabilityPayload($past))
        ->assertSessionHasErrors('tour_date_id');

    $inactive = availabilityFixture();
    $inactive['operator']->update(['is_active' => false]);

    $this->actingAs($inactive['tourist'])
        ->post(route('user.bookings.store'), availabilityPayload($inactive))
        ->assertSessionHasErrors('tour_date_id');
});

test('successful booking only notifies its operator and superadmins', function () {
    $f = availabilityFixture();
    $other = availabilityFixture();

    $role = Role::query()->firstOrCreate(['name' => 'Admin']);

    $ownedAdmin = User::factory()->create([
        'role_id' => $role->id,
        'tour_operator_id' => $f['operator']->id,
    ]);

    $foreignAdmin = User::factory()->create([
        'role_id' => $role->id,
        'tour_operator_id' => $other['operator']->id,
    ]);

    $superRole = Role::query()->firstOrCreate(['name' => 'Superadmin']);
    $super = User::factory()->create(['role_id' => $superRole->id]);

    $this->actingAs($f['tourist'])
        ->post(route('user.bookings.store'), availabilityPayload($f))
        ->assertRedirect();

    Notification::assertSentTo($ownedAdmin, BookingCreatedNotification::class);
    Notification::assertNotSentTo($foreignAdmin, BookingCreatedNotification::class);
    Notification::assertSentTo($super, BookingCreatedNotification::class);
});
