<?php

use App\Models\Booking;
use App\Models\Package;
use App\Models\PickupLocation;
use App\Models\PickupSchedule;
use App\Models\Role;
use App\Models\TourDate;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('guests are redirected from the booking calendar', function () {
    $this->get(route('admin.bookings.calendar'))
        ->assertRedirect(route('login'));
});

test('tourists cannot open the booking calendar', function () {
    $tourist = User::factory()->create([
        'role_id' => Role::where('name', 'User')->first()->id,
    ]);

    $this->actingAs($tourist)
        ->get(route('admin.bookings.calendar'))
        ->assertForbidden();
});

test('staff can see upcoming booked days in date order', function () {
    $admin = User::factory()->create([
        'role_id' => Role::where('name', 'Superadmin')->first()->id,
    ]);

    $guest = User::factory()->create([
        'role_id' => Role::where('name', 'User')->first()->id,
        'name' => 'Maria Santos',
    ]);

    $package = Package::create([
        'package_name' => 'Siargao Hop',
        'destination' => 'Siargao',
        'price' => 1800,
        'status' => 'active',
    ]);

    $pickup = PickupLocation::create([
        'name' => 'General Luna',
        'status' => 'active',
    ]);

    $today = createTourDate($package, $pickup, now()->toDateString());
    $tomorrow = createTourDate($package, $pickup, now()->addDay()->toDateString());
    $later = createTourDate($package, $pickup, now()->addDays(3)->toDateString());
    $yesterday = createTourDate($package, $pickup, now()->subDay()->toDateString());

    createBooking($guest, $tomorrow, 'confirmed', 4);
    createBooking($guest, $today, 'pending', 2);
    createBooking($guest, $today, 'pending', 1);
    createBooking($guest, $later, 'cancelled', 3);
    createBooking($guest, $yesterday, 'confirmed', 2);

    $archived = createBooking($guest, $later, 'confirmed', 5);
    $archived->update([
        'is_archived' => true,
        'archived_at' => now(),
    ]);

    $this->actingAs($admin)
        ->get(route('admin.bookings.calendar'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/bookings/Calendar')
            ->where('today', now()->toDateString())
            ->has('days', 2)
            ->where('days.0.date', now()->toDateString())
            ->where('days.0.bookings_count', 2)
            ->where('days.0.guests', 3)
            ->where('days.0.bookings.0.customer', 'Maria Santos')
            ->where('days.0.bookings.0.package_name', 'Siargao Hop')
            ->where('days.0.bookings.0.status', 'pending')
            ->where('days.1.date', now()->addDay()->toDateString())
            ->where('days.1.bookings_count', 1)
            ->where('days.1.guests', 4)
            ->where('days.1.bookings.0.status', 'confirmed')
        );
});

function createTourDate(Package $package, PickupLocation $pickup, string $date): TourDate
{
    $tourDate = TourDate::create([
        'package_id' => $package->id,
        'tour_date' => $date,
        'capacity' => 20,
    ]);

    PickupSchedule::create([
        'tour_date_id' => $tourDate->id,
        'pickup_location_id' => $pickup->id,
        'pickup_time' => '07:00:00',
    ]);

    return $tourDate->load('pickupSchedules');
}

function createBooking(User $guest, TourDate $tourDate, string $status, int $guests): Booking
{
    return Booking::create([
        'user_id' => $guest->id,
        'tour_date_id' => $tourDate->id,
        'pickup_schedule_id' => $tourDate->pickupSchedules->first()->id,
        'number_of_guests' => $guests,
        'phone_number' => '09171234567',
        'nationality' => 'Filipino',
        'booking_status' => $status,
    ]);
}
