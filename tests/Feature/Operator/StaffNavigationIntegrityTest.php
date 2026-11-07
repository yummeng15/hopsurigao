
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
 * Creates isolated Operator data for navigation tests.
 *
 * @return array{
 *   operator: TourOperator,
 *   admin: User,
 *   booking: Booking
 * }
 */
function navigationFixture(string $label): array
{
    $operator = TourOperator::query()->create([
        'name' => 'Nav '.$label,
        'slug' => 'nav-'.Str::uuid(),
        'is_active' => true,
    ]);

    $adminRole = Role::query()->firstOrCreate([
        'name' => 'Admin',
    ]);

    $touristRole = Role::query()->firstOrCreate([
        'name' => 'User',
    ]);

    $admin = User::factory()->create([
        'role_id' => $adminRole->id,
    ]);

    $admin->forceFill([
        'tour_operator_id' => $operator->id,
    ])->save();

    $tourist = User::factory()->create([
        'role_id' => $touristRole->id,
    ]);

    $package = DB::table('packages')->insertGetId([
        'tour_operator_id' => $operator->id,
        'package_name' => 'Nav '.$label.' Tour',
        'boat_name' => 'Nav Boat',
        'destination' => 'Daku Island',
        'price' => 1500,
        'status' => 'active',
        'under_maintenance' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Inactive package for badge testing.
    DB::table('packages')->insert([
        'tour_operator_id' => $operator->id,
        'package_name' => 'Nav '.$label.' Inactive Tour',
        'boat_name' => 'Reserve Boat',
        'destination' => 'Daku Island',
        'price' => 1500,
        'status' => 'inactive',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $date = DB::table('tour_dates')->insertGetId([
        'package_id' => $package,
        'tour_date' => today()->addDays(4)->toDateString(),
        'capacity' => 12,
        'travel_closed' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $pickupLocation = DB::table('pickup_locations')
        ->insertGetId([
            'tour_operator_id' => $operator->id,
            'name' => 'Nav Pier '.$label.' '.Str::random(8),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

    // Inactive pickup location for badge testing.
    DB::table('pickup_locations')->insert([
        'tour_operator_id' => $operator->id,
        'name' => 'Inactive Pier '.$label.' '.Str::random(8),
        'status' => 'inactive',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $pickup = DB::table('pickup_schedules')
        ->insertGetId([
            'tour_date_id' => $date,
            'pickup_location_id' => $pickupLocation,
            'pickup_time' => '07:00:00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

    $booking = Booking::query()->create([
        'user_id' => $tourist->id,
        'tour_date_id' => $date,
        'pickup_schedule_id' => $pickup,
        'number_of_guests' => 2,
        'phone_number' => '09123456789',
        'nationality' => 'Filipino',
        'booking_status' => 'pending',
    ]);

    Payment::query()->create([
        'booking_id' => $booking->id,
        'amount' => 1500,
        'payment_method' => 'gcash',
        'gateway' => 'paymongo',
        'gateway_checkout_id' =>
            'cs_nav_'.Str::random(14),
        'payment_status' => 'pending',
        'paid_at' => null,
    ]);

    return compact(
        'operator',
        'admin',
        'booking'
    );
}

/*
|--------------------------------------------------------------------------
| TEST 1 — DASHBOARD AND OPERATIONS ACCESS
|--------------------------------------------------------------------------
*/

test('operator dashboard and operations page are accessible through the correct routes', function () {
    $own = navigationFixture('Own');

    $this->actingAs($own['admin'])
        ->get(route('operator.dashboard'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('operator/Dashboard')
        );

    $this->get(route('operator.operations.index'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('operator/Operations')
        );

    // Obsolete shared Admin page remains forbidden.
    $this->get(route('admin.boat-maintenance.index'))
        ->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| TEST 2 — BOAT MAINTENANCE SHORTCUT
|--------------------------------------------------------------------------
*/

test('the operator dashboard Boat Maintenance shortcut points to owned Operations', function () {
    $source = file_get_contents(
        resource_path('js/pages/operator/Dashboard.vue')
    );

    expect($source)
        ->toContain("href: '/operator/operations'")
        ->not->toContain("href: '/admin/boat-maintenance'");
});

test('operator operations appears as a sidebar group with separate section links', function () {
    $source = file_get_contents(
        resource_path('js/navigation/operator.ts')
    );

    expect($source)
        ->toContain("title: 'Operations'")
        ->toContain("href: '/operator/operations/boats'")
        ->toContain("href: '/operator/operations/weather'")
        ->toContain("href: '/operator/operations/pickups'")
        ->toContain("href: '/operator/operations/feedback'")
        ->toContain("href: '/operator/operations/reports'")
        ->not->toContain('Operations (Boats, Weather, Pickups, Feedback, Reports)');
});

test('staff portal group parents expand without navigating away', function () {
    $frame = file_get_contents(
        resource_path('js/components/portal/StaffPortalFrame.vue')
    );

    expect($frame)
        ->toContain('function visitGroup')
        ->toContain('toggleGroup(item)')
        ->not->toContain('router.visit(String(item.href))');
});

/*
|--------------------------------------------------------------------------
| TEST 3 — MOBILE OPERATOR NAVIGATION
|--------------------------------------------------------------------------
*/

test('operator mobile navigation never targets blocked legacy admin actions', function () {
    $source = file_get_contents(
        resource_path('js/navigation/mobile.ts')
    );

    $operatorTabs = explode(
        'export const touristMobileTabs',
        $source,
        2
    )[0];

    expect($operatorTabs)
        ->toContain("href: '/operator/qr-scanner'")
        ->toContain("href: '/operator/bookings'")
        ->toContain("href: '/operator/walk-ins/create'")
        ->not->toContain("href: '/admin/qrscanner'")
        ->not->toContain("href: '/admin/bookings/create'");
});

/*
|--------------------------------------------------------------------------
| TEST 4 — OPERATOR-SPECIFIC COUNTS
|--------------------------------------------------------------------------
*/

test('operator badge counts include its own records but not another business records', function () {
    $own = navigationFixture('OwnCounts');

    navigationFixture('OtherCounts');

    $this->actingAs($own['admin'])
        ->get(route('operator.dashboard'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('operator/Dashboard')
                ->where('badgeCounts.pending_bookings', 1)
                ->where('badgeCounts.pending_payments', 1)
                ->where('badgeCounts.inactive_packages', 1)
                ->where('badgeCounts.inactive_pickup_locations', 1)
        );
});

/*
|--------------------------------------------------------------------------
| TEST 5 — NO CROSS-OPERATOR BADGE LEAK
|--------------------------------------------------------------------------
*/

test('an admin with no own bookings sees zero badge counts despite other businesses data', function () {
    navigationFixture('OtherData');

    $emptyOperator = TourOperator::query()->create([
        'name' => 'Empty Operator',
        'slug' => 'nav-empty-'.Str::uuid(),
        'is_active' => true,
    ]);

    $admin = User::factory()->create([
        'role_id' => Role::query()
            ->firstOrCreate(['name' => 'Admin'])
            ->id,
    ]);

    $admin->forceFill([
        'tour_operator_id' => $emptyOperator->id,
    ])->save();

    $this->actingAs($admin)
        ->get(route('operator.dashboard'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->where('badgeCounts.pending_bookings', 0)
                ->where('badgeCounts.pending_payments', 0)
                ->where('badgeCounts.inactive_packages', 0)
                ->where('badgeCounts.inactive_pickup_locations', 0)
        );
});
