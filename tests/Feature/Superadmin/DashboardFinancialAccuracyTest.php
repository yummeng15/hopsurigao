
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
 * @return array{
 *   booking: Booking,
 *   package_id: int,
 *   superadmin: User
 * }
 */
function dashboardFinanceFixture(): array
{
    $operator = TourOperator::query()->create([
        'name' => 'Dashboard Finance '.Str::random(8),
        'slug' => 'dashboard-finance-'.Str::uuid(),
        'is_active' => true,
    ]);

    $packageId = DB::table('packages')->insertGetId([
        'tour_operator_id' => $operator->id,
        'package_name' => 'Finance Island Hopping Tour',
        'boat_name' => 'Finance Boat',
        'destination' => 'Daku Island',
        'price' => 1500,
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $dateId = DB::table('tour_dates')->insertGetId([
        'package_id' => $packageId,
        'tour_date' => today()
            ->addDays(8)
            ->toDateString(),
        'capacity' => 20,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $locationId = DB::table('pickup_locations')
        ->insertGetId([
            'tour_operator_id' => $operator->id,
            'name' => 'Finance Pier '.Str::random(8),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

    $pickupId = DB::table('pickup_schedules')
        ->insertGetId([
            'tour_date_id' => $dateId,
            'pickup_location_id' => $locationId,
            'pickup_time' => '07:00:00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

    $tourist = User::factory()->create([
        'role_id' => Role::query()
            ->firstOrCreate(['name' => 'User'])
            ->id,
    ]);

    $superadmin = User::factory()->create([
        'role_id' => Role::query()
            ->firstOrCreate(['name' => 'Superadmin'])
            ->id,
    ]);

    // Original agreement: 2 guests x 1500 = 3000.
    $booking = Booking::query()->create([
        'user_id' => $tourist->id,
        'tour_date_id' => $dateId,
        'pickup_schedule_id' => $pickupId,
        'number_of_guests' => 2,
        'phone_number' => '09123456789',
        'nationality' => 'Filipino',
        'booking_status' => 'confirmed',
    ]);

    return [
        'booking' => $booking,
        'package_id' => $packageId,
        'superadmin' => $superadmin,
    ];
}

function dashboardBookingRow(
    mixed $items,
    int $bookingId
): ?array {
    return collect($items)
        ->firstWhere('id', $bookingId);
}

/*
|--------------------------------------------------------------------------
| TEST 1 — SAVED BOOKING PRICE
|--------------------------------------------------------------------------
*/

test('dashboard keeps original price after package price changes', function () {
    $fixture = dashboardFinanceFixture();

    Payment::query()->create([
        'booking_id' => $fixture['booking']->id,
        'amount' => 1500,
        'payment_method' => 'gcash',
        'gateway' => 'paymongo',
        'payment_status' => 'partial',
        'paid_at' => now(),
    ]);

    // Package price is changed AFTER the booking.
    DB::table('packages')
        ->where('id', $fixture['package_id'])
        ->update([
            'price' => 2750,
        ]);

    $this->actingAs($fixture['superadmin'])
        ->get(route('superadmin.dashboard'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('superadmin/Dashboard')
                ->where(
                    'recentBookings',
                    function ($items) use ($fixture): bool {
                        $row = dashboardBookingRow(
                            $items,
                            $fixture['booking']->id
                        );

                        return $row !== null
                            && (float) $row['total_amount']
                                === 3000.0
                            && (float) $row['amount_received']
                                === 1500.0
                            && (float) $row['remaining_balance']
                                === 1500.0
                            && $row['payment_status']
                                === 'partial'
                            && $row['price_needs_review']
                                === false;
                    }
                )
        );
});

/*
|--------------------------------------------------------------------------
| TEST 2 — GCASH + CASH = PAID
|--------------------------------------------------------------------------
*/

test('dashboard totals downpayment and cash balance without double counting', function () {
    $fixture = dashboardFinanceFixture();

    $baselineRevenue = (float) Payment::query()
        ->whereIn(
            'payment_status',
            ['paid', 'partial']
        )
        ->sum('amount');

    // Tourist paid 50% using GCash.
    Payment::query()->create([
        'booking_id' => $fixture['booking']->id,
        'amount' => 1500,
        'payment_method' => 'gcash',
        'gateway' => 'paymongo',
        'payment_status' => 'partial',
        'paid_at' => now(),
    ]);

    // Operator collected remaining cash.
    Payment::query()->create([
        'booking_id' => $fixture['booking']->id,
        'amount' => 1500,
        'payment_method' => 'cash',
        'payment_status' => 'paid',
        'paid_at' => now(),
    ]);

    // A pending attempt must not count as revenue.
    Payment::query()->create([
        'booking_id' => $fixture['booking']->id,
        'amount' => 1500,
        'payment_method' => 'gcash',
        'payment_status' => 'pending',
    ]);

    $this->actingAs($fixture['superadmin'])
        ->get(route('superadmin.dashboard'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->where(
                    'stats.total_revenue',
                    fn ($value): bool =>
                        abs(
                            (float) $value
                            - ($baselineRevenue + 3000.0)
                        ) < 0.01
                )
                ->where(
                    'recentBookings',
                    function ($items) use ($fixture): bool {
                        $row = dashboardBookingRow(
                            $items,
                            $fixture['booking']->id
                        );

                        return $row !== null
                            && $row['payment_status'] === 'paid'
                            && (float) $row['amount_received']
                                === 3000.0
                            && (float) $row['remaining_balance']
                                === 0.0;
                    }
                )
        );
});

/*
|--------------------------------------------------------------------------
| TEST 3 — LEGACY PRICE REVIEW
|--------------------------------------------------------------------------
*/

test('older estimated booking prices require financial review', function () {
    $fixture = dashboardFinanceFixture();

    // Simulate a legacy migrated reservation.
    DB::table('bookings')
        ->where('id', $fixture['booking']->id)
        ->update([
            'price_snapshot_source' => 'legacy_estimate',
        ]);

    Payment::query()->create([
        'booking_id' => $fixture['booking']->id,
        'amount' => 3000,
        'payment_method' => 'cash',
        'payment_status' => 'paid',
        'paid_at' => now(),
    ]);

    $this->actingAs($fixture['superadmin'])
        ->get(route('superadmin.dashboard'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->where(
                    'recentBookings',
                    function ($items) use ($fixture): bool {
                        $row = dashboardBookingRow(
                            $items,
                            $fixture['booking']->id
                        );

                        return $row !== null
                            && $row['price_needs_review']
                                === true
                            && $row['payment_status']
                                === 'review';
                    }
                )
        );
});
