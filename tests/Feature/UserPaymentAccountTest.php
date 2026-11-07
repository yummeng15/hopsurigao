<?php

use App\Models\Booking;
use App\Models\PickupSchedule;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

test('the payment page stays closed for online payment until a PayMongo key is set', function () {
    $touristRole = Role::query()->where('name', 'User')->first();

    expect($touristRole)->not->toBeNull();

    $tourist = User::factory()->create([
        'role_id' => $touristRole->id,
    ]);

    $schedule = PickupSchedule::query()
        ->with('tourDate.package')
        ->get()
        ->first(function (PickupSchedule $schedule) {
            $tourDate = $schedule->tourDate;

            if ($tourDate === null || $tourDate->package?->status !== 'active') {
                return false;
            }

            $bookedGuests = $tourDate->bookings()
                ->whereIn('booking_status', ['pending', 'confirmed'])
                ->sum('number_of_guests');

            return ($tourDate->capacity - $bookedGuests) >= 1;
        });

    expect($schedule)->not->toBeNull();

    $this->actingAs($tourist)
        ->withSession(['_token' => 'test-token'])
        ->post(route('user.bookings.store'), [
            '_token' => 'test-token',
            'tour_date_id' => $schedule->tour_date_id,
            'pickup_schedule_id' => $schedule->id,
            'pickup_latitude' => 9.7845123,
            'pickup_longitude' => 126.1604321,
            'number_of_guests' => 1,
            'phone_number' => '09170005555',
            'nationality' => 'Filipino',
        ])
        ->assertRedirect();

    $booking = Booking::query()->where('user_id', $tourist->id)->latest('id')->first();

    expect($booking)->not->toBeNull();

    $this->actingAs($tourist)
        ->get(route('user.bookings.payment', $booking))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('user/bookings/Payment')
            ->where('paymongoEnabled', false)
        );
});
