<?php

use App\Models\Booking;
use App\Models\PickupSchedule;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

test('a tourist can pin the exact pickup spot when booking', function () {
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
            'phone_number' => '09170004444',
            'nationality' => 'Filipino',
        ])
        ->assertRedirect();

    $booking = Booking::query()
        ->where('user_id', $tourist->id)
        ->where('tour_date_id', $schedule->tour_date_id)
        ->first();

    expect($booking)->not->toBeNull()
        ->and((float) $booking->pickup_latitude)->toBe(9.7845123)
        ->and((float) $booking->pickup_longitude)->toBe(126.1604321);
});
