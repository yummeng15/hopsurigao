
<?php

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Role;
use App\Models\TourOperator;
use App\Models\User;
use App\Services\PayMongoCheckoutService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    $this->withoutVite();
});

/** @return array{booking: Booking, package_id: int, operator: TourOperator} */
function priceSnapshotFixture(): array
{
    $operator = TourOperator::query()->create([
        'name' => 'Price Test Operator',
        'slug' => 'price-test-'.Str::uuid(),
        'is_active' => true,
    ]);

    $packageId = DB::table('packages')->insertGetId([
        'tour_operator_id' => $operator->id,
        'package_name' => 'Price Snapshot Island Tour',
        'boat_name' => 'Snapshot Boat',
        'destination' => 'Daku Island',
        'price' => '1500.00',
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $dateId = DB::table('tour_dates')->insertGetId([
        'package_id' => $packageId,
        'tour_date' => today()->addDays(11)->toDateString(),
        'capacity' => 20,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $locationId = DB::table('pickup_locations')->insertGetId([
        'name' => 'Snapshot Pier',
        'tour_operator_id' => $operator->id,
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $pickupId = DB::table('pickup_schedules')->insertGetId([
        'tour_date_id' => $dateId,
        'pickup_location_id' => $locationId,
        'pickup_time' => '07:00:00',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $role = Role::query()->firstOrCreate(['name' => 'User']);
    $tourist = User::factory()->create(['role_id' => $role->id]);

    // This is the same Eloquent creation path used by Tourist and Walk-in.
    $booking = Booking::query()->create([
        'user_id' => $tourist->id,
        'tour_date_id' => $dateId,
        'pickup_schedule_id' => $pickupId,
        'number_of_guests' => 2,
        'phone_number' => '09123456789',
        'nationality' => 'Filipino',
        'booking_status' => 'pending',
    ]);

    return compact('booking', 'operator') + ['package_id' => $packageId];
}

test('new reservations save the agreed price at creation', function () {
    $fixture = priceSnapshotFixture();
    $booking = $fixture['booking']->fresh();

    expect((float) $booking->booked_unit_price)->toBe(1500.0)
        ->and((float) $booking->booked_total)->toBe(3000.0)
        ->and($booking->price_snapshot_source)->toBe('at_booking')
        ->and($booking->packageTotal())->toBe(3000.0)
        ->and($booking->totalCentavos())->toBe(300000);
});

test('later catalog price changes do not alter the booking or PayMongo charge', function () {
    $fixture = priceSnapshotFixture();
    $booking = $fixture['booking'];

    DB::table('packages')
        ->where('id', $fixture['package_id'])
        ->update(['price' => '2750.00']);

    $booking = $booking->fresh();
    $service = app(PayMongoCheckoutService::class);

    expect($booking->packageTotal())->toBe(3000.0)
        ->and($service->centavosFor($booking))->toBe(300000)
        ->and($service->chargeCentavos($booking, 'downpayment'))->toBe(300000)
        ->and($service->chargeCentavos($booking, 'full'))->toBe(300000);
});

test('cash balance keeps the original quote after a package price change', function () {
    $fixture = priceSnapshotFixture();
    $booking = $fixture['booking'];
    $booking->update(['booking_status' => 'confirmed']);

    Payment::query()->create([
        'booking_id' => $booking->id,
        'amount' => 1500,
        'gateway' => 'paymongo',
        'payment_method' => 'gcash',
        'payment_status' => 'partial',
        'paid_at' => now(),
    ]);

    DB::table('packages')
        ->where('id', $fixture['package_id'])
        ->update(['price' => '2750.00']);

    $adminRole = Role::query()->firstOrCreate(['name' => 'Admin']);
    $admin = User::factory()->create([
        'role_id' => $adminRole->id,
        'tour_operator_id' => $fixture['operator']->id,
    ]);

    $this->actingAs($admin, 'web')
        ->get(route('operator.payments.show', $booking->id))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('operator/PaymentDetails')
            ->where('booking.total', fn ($v) => (float) $v === 3000.0)
            ->where('booking.remaining', fn ($v) => (float) $v === 1500.0)
            ->where('booking.canCollect', true)
        );

    $this->post(route('operator.payments.collect', $booking->id), [
        'amount' => '1500.00',
        'collection_key' => (string) Str::uuid(),
        'cash_received' => true,
    ])->assertRedirect(route('operator.payments.show', $booking->id));

    expect($booking->fresh()->remainingBalance())->toBe(0.0)
        ->and((float) DB::table('payments')
            ->where('booking_id', $booking->id)
            ->where('payment_method', 'cash')
            ->sum('amount'))->toBe(1500.0);
});

test('the agreed price and guest count cannot be modified after reservation', function () {
    $fixture = priceSnapshotFixture();
    $booking = $fixture['booking'];

    expect(fn () => $booking->update(['number_of_guests' => 5]))
        ->toThrow(ValidationException::class);

    expect(fn () => $booking->forceFill(['booked_total' => '10.00'])->save())
        ->toThrow(ValidationException::class);

    $saved = $booking->fresh();

    expect($saved->number_of_guests)->toBe(2)
        ->and((float) $saved->booked_total)->toBe(3000.0);
});
