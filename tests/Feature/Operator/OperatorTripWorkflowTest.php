
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

function tripFixture(string $dateOffset = 'today'): array
{
    $operator = TourOperator::create([
        'name' => 'Trip Test '.Str::random(8),
        'slug' => 'trip-test-'.Str::uuid(),
        'is_active' => true,
    ]);

    $adminRole = Role::firstOrCreate(['name' => 'Admin']);
    $touristRole = Role::firstOrCreate(['name' => 'User']);

    $admin = User::factory()->create([
        'role_id' => $adminRole->id,
    ]);

    $admin->forceFill([
        'tour_operator_id' => $operator->id,
    ])->save();

    $tourist = User::factory()->create([
        'role_id' => $touristRole->id,
    ]);

    $packageId = DB::table('packages')->insertGetId([
        'tour_operator_id' => $operator->id,
        'package_name' => 'Trip Test Island Tour',
        'boat_name' => 'Test Boat',
        'destination' => 'Daku Island',
        'price' => 1500,
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $tourDateId = DB::table('tour_dates')->insertGetId([
        'package_id' => $packageId,
        'tour_date' => $dateOffset === 'future'
            ? today()->addDays(2)->toDateString()
            : today()->toDateString(),
        'capacity' => 10,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $locationId = DB::table('pickup_locations')->insertGetId([
        'tour_operator_id' => $operator->id,
        'name' => 'Trip Pier '.Str::random(8),
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $scheduleId = DB::table('pickup_schedules')->insertGetId([
        'tour_date_id' => $tourDateId,
        'pickup_location_id' => $locationId,
        'pickup_time' => '07:00:00',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $booking = Booking::create([
        'user_id' => $tourist->id,
        'tour_date_id' => $tourDateId,
        'pickup_schedule_id' => $scheduleId,
        'number_of_guests' => 2,
        'phone_number' => '09123456789',
        'nationality' => 'Filipino',
        'booking_status' => 'confirmed',
    ]);

    return compact('admin', 'tourist', 'operator', 'booking');
}

test('scanning a valid QR opens trip management with one-time check-in option', function () {
    $f = tripFixture();
    $this->actingAs($f['admin']);

    $this->post(route('operator.qr.verify'), [
        'qr_token' => $f['booking']->qr_token,
    ])->assertRedirect(
        route('operator.trips.show', $f['booking']->id)
    );

    $this->get(route('operator.trips.show', $f['booking']->id))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('operator/TripManagement')
            ->where('scannedToken', $f['booking']->qr_token)
        );
});

test('verified QR check-in and fully paid confirmed trip can be completed', function () {
    $f = tripFixture();
    $this->actingAs($f['admin']);

    $payment = Payment::create([
        'booking_id' => $f['booking']->id,
        'amount' => 3000,
        'payment_method' => 'cash',
        'payment_status' => 'paid',
        'paid_at' => now(),
    ]);

    $this->post(route('operator.trips.check-in', $f['booking']->id), [
        'qr_token' => $f['booking']->qr_token,
    ])->assertRedirect(
        route('operator.trips.show', $f['booking']->id)
    );

    $this->post(route('operator.trips.outcome', $f['booking']->id), [
        'outcome' => 'completed',
    ])->assertRedirect(
        route('operator.trips.show', $f['booking']->id)
    );

    expect($f['booking']->fresh()->booking_status)
        ->toBe('completed')
        ->and($payment->fresh()->payment_status)
        ->toBe('paid');

    $this->assertDatabaseHas('booking_trip_records', [
        'booking_id' => $f['booking']->id,
        'outcome' => 'completed',
    ]);
});

test('unpaid booking cannot be completed even when checked in', function () {
    $f = tripFixture();
    $this->actingAs($f['admin']);

    $this->post(route('operator.trips.check-in', $f['booking']->id), [
        'qr_token' => $f['booking']->qr_token,
    ])->assertRedirect();

    $this->post(route('operator.trips.outcome', $f['booking']->id), [
        'outcome' => 'completed',
    ])->assertSessionHasErrors('outcome');

    expect($f['booking']->fresh()->booking_status)
        ->toBe('confirmed');
});

test('passenger no-show is recorded without changing booking or financial records', function () {
    $f = tripFixture();

    $this->actingAs($f['admin'])
        ->post(route('operator.trips.outcome', $f['booking']->id), [
            'outcome' => 'passenger_no_show',
            'notes' => 'Passenger did not arrive at the pickup station.',
        ])->assertRedirect();

    $this->assertDatabaseHas('booking_trip_records', [
        'booking_id' => $f['booking']->id,
        'outcome' => 'passenger_no_show',
    ]);

    expect($f['booking']->fresh()->booking_status)
        ->toBe('confirmed');

    $this->post(route('operator.trips.outcome', $f['booking']->id), [
        'outcome' => 'operator_no_show',
        'notes' => 'Attempt to overwrite no-show.',
    ])->assertSessionHasErrors('outcome');
});

test('operator no-show can be reported but cannot auto-cancel or refund the booking', function () {
    $f = tripFixture();

    $this->actingAs($f['admin'])
        ->post(route('operator.trips.outcome', $f['booking']->id), [
            'outcome' => 'operator_no_show',
            'notes' => 'Boat did not reach the agreed pickup station.',
        ])->assertRedirect();

    $this->assertDatabaseHas('booking_trip_records', [
        'booking_id' => $f['booking']->id,
        'outcome' => 'operator_no_show',
    ]);

    expect($f['booking']->fresh()->booking_status)
        ->toBe('confirmed');
});

test('another operator cannot view or change the booking trip', function () {
    $own = tripFixture();
    $other = tripFixture();

    $this->actingAs($other['admin'])
        ->get(route('operator.trips.show', $own['booking']->id))
        ->assertNotFound();

    $this->post(route('operator.trips.check-in', $own['booking']->id), [
        'qr_token' => $own['booking']->qr_token,
    ])->assertNotFound();

    $this->post(route('operator.trips.outcome', $own['booking']->id), [
        'outcome' => 'passenger_no_show',
        'notes' => 'Cannot report another operator booking.',
    ])->assertNotFound();
});

test('future bookings cannot be checked in or assigned a trip outcome', function () {
    $f = tripFixture('future');

    $this->actingAs($f['admin'])
        ->post(route('operator.trips.check-in', $f['booking']->id), [
            'qr_token' => $f['booking']->qr_token,
        ])->assertSessionHasErrors('qr_token');

    $this->post(route('operator.trips.outcome', $f['booking']->id), [
        'outcome' => 'passenger_no_show',
        'notes' => 'Tour has not happened yet.',
    ])->assertSessionHasErrors('outcome');
});
