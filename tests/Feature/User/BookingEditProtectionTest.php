
<?php

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Role;
use App\Models\TourOperator;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    $this->withoutVite();
});

/**
 * Create an isolated pending Tourist booking.
 *
 * IMPORTANT:
 * The database permits only one pickup schedule
 * per (tour_date_id, pickup_location_id).
 *
 * Two pickup schedules therefore use two
 * DIFFERENT pickup locations on the same tour date.
 *
 * @return array{
 *     tourist: User,
 *     booking: Booking,
 *     pickup: int,
 *     other_pickup: int
 * }
 */
function pendingEditFixture(): array
{
    $operator = TourOperator::query()->create([
        'name' => 'Pending Edit Test',
        'slug' => 'pending-edit-'.Str::uuid(),
        'is_active' => true,
    ]);

    $packageId = DB::table('packages')->insertGetId([
        'tour_operator_id' => $operator->id,
        'package_name' => 'Protected Edit Island Tour',
        'boat_name' => 'Edit Test Boat',
        'destination' => 'Daku Island',
        'price' => 1500,
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $tourDateId = DB::table('tour_dates')->insertGetId([
        'package_id' => $packageId,
        'tour_date' => today()->addDays(12)->toDateString(),
        'capacity' => 20,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    /*
    |--------------------------------------------------------------------------
    | PICKUP LOCATION 1
    |--------------------------------------------------------------------------
    |
    | Main Pier for the 7:00 AM schedule.
    |
    */

    $mainLocationId = DB::table('pickup_locations')
        ->insertGetId([
            'tour_operator_id' => $operator->id,
            'name' => 'Main Pier',
            'latitude' => 9.86,
            'longitude' => 126.08,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

    $pickup = DB::table('pickup_schedules')
        ->insertGetId([
            'tour_date_id' => $tourDateId,
            'pickup_location_id' => $mainLocationId,
            'pickup_time' => '07:00:00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

    /*
    |--------------------------------------------------------------------------
    | PICKUP LOCATION 2
    |--------------------------------------------------------------------------
    |
    | Another location, but still on the SAME tour date.
    |
    | This satisfies the unique database constraint:
    |
    | tour_date_id + pickup_location_id
    |
    */

    $alternateLocationId = DB::table('pickup_locations')
        ->insertGetId([
            'tour_operator_id' => $operator->id,
            'name' => 'Alternate Pier',
            'latitude' => 9.862,
            'longitude' => 126.082,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

    $otherPickup = DB::table('pickup_schedules')
        ->insertGetId([
            'tour_date_id' => $tourDateId,
            'pickup_location_id' => $alternateLocationId,
            'pickup_time' => '08:00:00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

    /*
    |--------------------------------------------------------------------------
    | TOURIST ACCOUNT
    |--------------------------------------------------------------------------
    */

    $role = Role::query()->firstOrCreate([
        'name' => 'User',
    ]);

    $tourist = User::factory()->create([
        'role_id' => $role->id,
    ]);

    /*
    |--------------------------------------------------------------------------
    | TOURIST BOOKING
    |--------------------------------------------------------------------------
    */

    $booking = Booking::query()->create([
        'user_id' => $tourist->id,
        'tour_date_id' => $tourDateId,
        'pickup_schedule_id' => $pickup,
        'pickup_latitude' => 9.86,
        'pickup_longitude' => 126.08,
        'number_of_guests' => 2,
        'phone_number' => '09123456789',
        'nationality' => 'Filipino',
        'special_request' => null,
        'booking_status' => 'pending',
    ]);

    return [
        'tourist' => $tourist,
        'booking' => $booking,
        'pickup' => $pickup,
        'other_pickup' => $otherPickup,
    ];
}

/**
 * Valid editable booking fields.
 */
function pendingEditPayload(int $schedule): array
{
    return [
        'pickup_schedule_id' => $schedule,
        'pickup_latitude' => 9.861,
        'pickup_longitude' => 126.081,
        'phone_number' => '09991234567',
        'nationality' => 'Filipino',
        'special_request' => 'Please call before pickup.',
    ];
}

/*
|--------------------------------------------------------------------------
| TEST 1 — EDIT FORM
|--------------------------------------------------------------------------
*/

test('editing shows saved booking totals and allowed pickup options', function () {
    $data = pendingEditFixture();

    $this->actingAs($data['tourist'], 'web')
        ->get(
            route('user.bookings.edit', $data['booking'])
        )
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('user/bookings/Edit')
                ->where(
                    'booking.number_of_guests',
                    2
                )
                ->where(
                    'booking.booked_total',
                    fn ($value) =>
                        (float) $value === 3000.0
                )
                ->has(
                    'tourDate.pickup_schedules',
                    2
                )
        );
});

/*
|--------------------------------------------------------------------------
| TEST 2 — EDIT PICKUP AND CONTACT
|--------------------------------------------------------------------------
*/

test('tourist can edit pickup and contact without changing guests or agreed price', function () {
    $data = pendingEditFixture();

    $original = $data['booking']->fresh();

    $this->actingAs($data['tourist'], 'web')
        ->patch(
            route('user.bookings.update', $original),
            pendingEditPayload(
                $data['other_pickup']
            )
        )
        ->assertRedirect(
            route('user.bookings.show', $original)
        );

    $updated = $original->fresh();

    expect($updated->pickup_schedule_id)
        ->toBe($data['other_pickup'])

        ->and($updated->phone_number)
        ->toBe('09991234567')

        ->and($updated->special_request)
        ->toBe('Please call before pickup.')

        ->and($updated->tour_date_id)
        ->toBe($original->tour_date_id)

        ->and($updated->number_of_guests)
        ->toBe(2)

        ->and((float) $updated->booked_total)
        ->toBe(3000.0)

        ->and((float) $updated->booked_unit_price)
        ->toBe(1500.0);
});

/*
|--------------------------------------------------------------------------
| TEST 3 — REJECT PRICE AND GUEST CHANGES
|--------------------------------------------------------------------------
*/

test('tourist cannot submit a modified guest count or saved price', function () {
    $data = pendingEditFixture();

    $payload = pendingEditPayload(
        $data['pickup']
    );

    $payload['number_of_guests'] = 3;
    $payload['booked_total'] = 1;

    $this->actingAs($data['tourist'], 'web')
        ->patch(
            route(
                'user.bookings.update',
                $data['booking']
            ),
            $payload
        )
        ->assertSessionHasErrors([
            'number_of_guests',
            'booked_total',
        ]);

    $updated = $data['booking']->fresh();

    expect($updated->number_of_guests)
        ->toBe(2)

        ->and((float) $updated->booked_total)
        ->toBe(3000.0);
});

/*
|--------------------------------------------------------------------------
| TEST 4 — REJECT A DIFFERENT TOUR DATE
|--------------------------------------------------------------------------
*/

test('a pickup schedule for another tour date cannot be selected', function () {
    $data = pendingEditFixture();

    $other = pendingEditFixture();

    $this->actingAs($data['tourist'], 'web')
        ->patch(
            route(
                'user.bookings.update',
                $data['booking']
            ),
            pendingEditPayload(
                $other['pickup']
            )
        )
        ->assertSessionHasErrors(
            'pickup_schedule_id'
        );

    expect(
        $data['booking']->fresh()->pickup_schedule_id
    )->toBe(
        $data['pickup']
    );
});

/*
|--------------------------------------------------------------------------
| TEST 5 — PRESERVE PENDING PAYMONGO CHECKOUT
|--------------------------------------------------------------------------
*/

test('a pending PayMongo checkout is not changed by editing pickup details', function () {
    $data = pendingEditFixture();

    $payment = Payment::query()->create([
        'booking_id' => $data['booking']->id,
        'amount' => 1500,
        'payment_method' => 'gcash',
        'payment_status' => 'pending',
        'gateway' => 'paymongo',
        'gateway_checkout_id' =>
            'cs_test_edit_'.Str::random(8),
        'transaction_reference' =>
            'HS-'.$data['booking']->id,
        'paid_at' => null,
    ]);

    $this->actingAs(
        $data['tourist'],
        'web'
    )
        ->get(
            route(
                'user.bookings.edit',
                $data['booking']
            )
        )
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->where(
                    'booking.has_pending_checkout',
                    true
                )
        );

    $this->patch(
        route(
            'user.bookings.update',
            $data['booking']
        ),
        pendingEditPayload(
            $data['other_pickup']
        )
    )->assertRedirect();

    $after = $payment->fresh();

    expect($after->payment_status)
        ->toBe('pending')

        ->and((float) $after->amount)
        ->toBe(1500.0)

        ->and($after->gateway_checkout_id)
        ->toBe($payment->gateway_checkout_id)

        ->and(
            $data['booking']->fresh()->booking_status
        )
        ->toBe('pending');
});

/*
|--------------------------------------------------------------------------
| TEST 6 — TOURIST OWNERSHIP
|--------------------------------------------------------------------------
*/

test('another tourist cannot view or change a booking they do not own', function () {
    $data = pendingEditFixture();

    $otherTourist = User::factory()->create([
        'role_id' =>
            $data['tourist']->role_id,
    ]);

    $this->actingAs(
        $otherTourist,
        'web'
    )
        ->get(
            route(
                'user.bookings.edit',
                $data['booking']
            )
        )
        ->assertForbidden();

    $this->patch(
        route(
            'user.bookings.update',
            $data['booking']
        ),
        pendingEditPayload(
            $data['pickup']
        )
    )->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| TEST 7 — CONFIRMED AND PAID BOOKING PROTECTION
|--------------------------------------------------------------------------
*/

test('confirmed bookings and already-paid pending bookings cannot be edited', function () {
    $data = pendingEditFixture();

    $this->actingAs(
        $data['tourist'],
        'web'
    );

    /*
     * Confirmed bookings cannot be edited.
     */
    $data['booking']->update([
        'booking_status' => 'confirmed',
    ]);

    $this->get(
        route(
            'user.bookings.edit',
            $data['booking']
        )
    )->assertForbidden();

    $this->patch(
        route(
            'user.bookings.update',
            $data['booking']
        ),
        pendingEditPayload(
            $data['pickup']
        )
    )->assertForbidden();

    /*
     * Even a pending booking is locked if
     * a payment is already recorded.
     */
    $second = pendingEditFixture();

    Payment::query()->create([
        'booking_id' =>
            $second['booking']->id,

        'amount' => 1500,

        'payment_method' => 'gcash',

        'payment_status' => 'partial',

        'gateway' => 'paymongo',

        'paid_at' => now(),
    ]);

    $this->actingAs(
        $second['tourist'],
        'web'
    )
        ->get(
            route(
                'user.bookings.edit',
                $second['booking']
            )
        )
        ->assertForbidden();
});
