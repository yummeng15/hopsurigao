
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

function tripVisibilityAccount(
    string $role,
    ?TourOperator $operator = null
): User {
    $user = User::factory()->create([
        'role_id' => Role::query()
            ->firstOrCreate(['name' => $role])
            ->id,
    ]);

    if ($operator !== null) {
        $user->forceFill([
            'tour_operator_id' => $operator->id,
        ])->save();
    }

    return $user->refresh();
}

/**
 * @return array{
 *   operator: TourOperator,
 *   admin: User,
 *   tourist: User,
 *   booking: Booking
 * }
 */
function tripVisibilityFixture(
    string $outcome = 'passenger_no_show'
): array {
    $operator = TourOperator::query()->create([
        'name' => 'Trip Visibility '.Str::random(8),
        'slug' => 'trip-visibility-'.Str::uuid(),
        'is_active' => true,
    ]);

    $admin = tripVisibilityAccount('Admin', $operator);
    $tourist = tripVisibilityAccount('User');

    $packageId = DB::table('packages')->insertGetId([
        'tour_operator_id' => $operator->id,
        'package_name' => 'Trip Visibility Tour',
        'boat_name' => 'Demo Boat',
        'destination' => 'Daku Island',
        'price' => 1500,
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $dateId = DB::table('tour_dates')->insertGetId([
        'package_id' => $packageId,
        'tour_date' => today()->toDateString(),
        'capacity' => 20,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $locationId = DB::table('pickup_locations')->insertGetId([
        'tour_operator_id' => $operator->id,
        'name' => 'Trip Visibility Pier '.Str::random(6),
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

    $booking = Booking::query()->create([
        'user_id' => $tourist->id,
        'tour_date_id' => $dateId,
        'pickup_schedule_id' => $pickupId,
        'number_of_guests' => 2,
        'phone_number' => '09123456789',
        'nationality' => 'Filipino',
        'booking_status' => $outcome === 'completed'
            ? 'completed'
            : 'confirmed',
    ]);

    DB::table('booking_trip_records')->insert([
        'booking_id' => $booking->id,
        'outcome' => $outcome,
        'notes' => 'Operator report for staff review',
        'recorded_by' => $admin->id,
        'recorded_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return compact(
        'operator',
        'admin',
        'tourist',
        'booking'
    );
}

/*
|--------------------------------------------------------------------------
| TEST 1 — TOURIST VISIBILITY
|--------------------------------------------------------------------------
*/

test('tourist sees only their trip outcome reports on My Bookings', function () {
    $own = tripVisibilityFixture();
    $other = tripVisibilityFixture('operator_no_show');

    $this->actingAs($own['tourist'])
        ->get(route('user.bookings.index'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('user/bookings/Index')
                ->where('tripMonitor.mode', 'list')
                ->where(
                    'tripMonitor.records',
                    fn ($records) =>
                        collect($records)
                            ->pluck('booking_id')
                            ->all() === [
                                $own['booking']->id,
                            ]
                )
        );
});

/*
|--------------------------------------------------------------------------
| TEST 2 — OPERATOR ISOLATION
|--------------------------------------------------------------------------
*/

test('operator sees only trip outcomes of their own tour business', function () {
    $own = tripVisibilityFixture();
    $other = tripVisibilityFixture();

    $this->actingAs($own['admin'])
        ->get(route('operator.bookings.index'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('operator/Bookings')
                ->where('tripMonitor.mode', 'list')
                ->where(
                    'tripMonitor.records',
                    fn ($records) =>
                        collect($records)
                            ->pluck('booking_id')
                            ->all() === [
                                $own['booking']->id,
                            ]
                )
        );
});

/*
|--------------------------------------------------------------------------
| TEST 3 — SUPER ADMIN OVERSIGHT
|--------------------------------------------------------------------------
*/

test('superadmin sees operator trip outcomes across businesses', function () {
    $first = tripVisibilityFixture('passenger_no_show');
    $second = tripVisibilityFixture('operator_no_show');

    $superadmin = tripVisibilityAccount('Superadmin');

    $this->actingAs($superadmin)
        ->get(route('admin.bookings.index'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('admin/bookings/Index')
                ->where('tripMonitor.mode', 'list')
                ->where(
                    'tripMonitor.records',
                    fn ($records) =>
                        collect($records)
                            ->pluck('booking_id')
                            ->sort()
                            ->values()
                            ->all()
                        === collect([
                            $first['booking']->id,
                            $second['booking']->id,
                        ])
                            ->sort()
                            ->values()
                            ->all()
                )
        );
});

/*
|--------------------------------------------------------------------------
| TEST 4 — OPERATOR DETAILS
|--------------------------------------------------------------------------
*/

test('booking detail includes its separate trip outcome and staff report', function () {
    $own = tripVisibilityFixture('operator_no_show');

    $this->actingAs($own['admin'])
        ->get(
            route(
                'operator.bookings.show',
                $own['booking']->id
            )
        )
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('operator/BookingDetails')
                ->where('tripMonitor.mode', 'detail')
                ->where(
                    'tripMonitor.record.outcome',
                    'operator_no_show'
                )
                ->where(
                    'tripMonitor.record.booking_status',
                    'confirmed'
                )
                ->where(
                    'tripMonitor.record.notes',
                    'Operator report for staff review'
                )
        );
});

/*
|--------------------------------------------------------------------------
| TEST 5 — TOURIST DATA PRIVACY
|--------------------------------------------------------------------------
*/

test('tourist detail does not reveal private operator report notes', function () {
    $own = tripVisibilityFixture('passenger_no_show');

    $this->actingAs($own['tourist'])
        ->get(
            route(
                'user.bookings.show',
                $own['booking']->id
            )
        )
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('user/bookings/Show')
                ->where('tripMonitor.mode', 'detail')
                ->where(
                    'tripMonitor.record.outcome',
                    'passenger_no_show'
                )
                ->where(
                    'tripMonitor.record.notes',
                    null
                )
        );
});

/*
|--------------------------------------------------------------------------
| TEST 6 — NO-SHOW PAYMENT PROTECTION
|--------------------------------------------------------------------------
*/

test('no-show report blocks further operator cash collection without altering payments', function () {
    $own = tripVisibilityFixture('passenger_no_show');

    $payment = Payment::query()->create([
        'booking_id' => $own['booking']->id,
        'amount' => 1500,
        'payment_method' => 'gcash',
        'payment_status' => 'partial',
        'gateway' => 'paymongo',
        'gateway_checkout_id' =>
            'cs_test_'.Str::random(12),
        'transaction_reference' =>
            'pay_test_'.Str::random(12),
        'paid_at' => now(),
    ]);

    $this->actingAs($own['admin'])
        ->get(
            route(
                'operator.payments.show',
                $own['booking']->id
            )
        )
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('operator/PaymentDetails')
                ->where(
                    'booking.canCollect',
                    false
                )
                ->where(
                    'tripMonitor.record.outcome',
                    'passenger_no_show'
                )
        );

    $this->post(
        route(
            'operator.payments.collect',
            $own['booking']->id
        ),
        [
            'amount' => '1500.00',
            'cash_received' => true,
            'collection_key' => (string) Str::uuid(),
        ]
    )->assertSessionHasErrors('amount');

    expect($payment->fresh()->payment_status)
        ->toBe('partial')

        ->and(
            $own['booking']->fresh()->booking_status
        )
        ->toBe('confirmed')

        ->and(
            $own['booking']->payments()->count()
        )
        ->toBe(1);
});
