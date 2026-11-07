
<?php

use App\Models\Booking;
use App\Models\BookingFeedback;
use App\Models\Role;
use App\Models\TourOperator;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    config()->set('inertia.ssr.enabled', false);
    $this->withoutVite();
});

function opsFixture(string $name): array
{
    $operator = TourOperator::query()->create([
        'name' => $name,
        'slug' => Str::slug($name).'-'.Str::random(8),
        'is_active' => true,
    ]);

    $role = Role::query()->firstOrCreate([
        'name' => 'Admin',
    ]);

    $admin = User::factory()->create([
        'role_id' => $role->id,
    ]);

    $admin->forceFill([
        'tour_operator_id' => $operator->id,
    ])->save();

    $package = DB::table('packages')->insertGetId([
        'tour_operator_id' => $operator->id,
        'package_name' => $name.' tour',
        'boat_name' => $name.' boat',
        'destination' => 'Island',
        'price' => 1000,
        'status' => 'active',
        'under_maintenance' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $date = DB::table('tour_dates')->insertGetId([
        'package_id' => $package,
        'tour_date' => today()->addDays(10)->toDateString(),
        'capacity' => 10,
        'travel_closed' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $location = DB::table('pickup_locations')->insertGetId([
        'tour_operator_id' => $operator->id,
        'name' => $name.' Pier '.Str::random(5),
        'latitude' => 9.86,
        'longitude' => 126.08,
        'status' => 'active',
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

    $touristRole = Role::query()->firstOrCreate([
        'name' => 'User',
    ]);

    $tourist = User::factory()->create([
        'role_id' => $touristRole->id,
    ]);

    // Booking model saves the price snapshot from Fix 4A.
    $booking = Booking::query()->create([
        'user_id' => $tourist->id,
        'tour_date_id' => $date,
        'pickup_schedule_id' => $pickup,
        'number_of_guests' => 2,
        'phone_number' => '09123456789',
        'nationality' => 'Filipino',
        'booking_status' => 'pending',
    ]);

    BookingFeedback::query()->create([
        'booking_id' => $booking->id,
        'user_id' => $tourist->id,
        'smoothness' => 'smooth',
        'user_friendly' => 'yes',
        'ease' => 'easy',
        'comment' => $name.' private feedback',
    ]);

    return compact(
        'operator',
        'admin',
        'package',
        'date',
        'location',
        'booking'
    );
}

/*
|--------------------------------------------------------------------------
| TEST 1 — OPERATOR DATA VISIBILITY
|--------------------------------------------------------------------------
|
| FIXED:
| Shared pickup locations are allowed.
| Own private pickup location must be visible.
| Another operator's private location must stay hidden.
|
*/

test('operator operations shows only its own boats dates feedback and private locations plus shared locations', function () {
    $own = opsFixture('Own');
    $other = opsFixture('Other');

    $this->actingAs($own['admin'])
        ->get(route('operator.operations.index'))
        ->assertRedirect(route('operator.operations.boats'));

    $this->get(route('operator.operations.boats'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('operator/Operations')
                ->where('section', 'boats')
                ->has('boats', 1)
                ->where('boats.0.id', $own['package'])
                ->where('locations', [])
                ->where('dates', [])
                ->where('feedback', [])
        );

    $this->get(route('operator.operations.pickups'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('operator/Operations')
                ->where('section', 'pickups')
                ->where('boats', [])
                ->where('locations', function ($locations) use ($own, $other): bool {
                    $visible = collect($locations);

                    $containsOwn = $visible->contains(
                        fn ($location): bool =>
                            (int) data_get($location, 'id') === (int) $own['location']
                            && data_get($location, 'is_shared') === false
                    );

                    $containsOther = $visible->contains(
                        fn ($location): bool =>
                            (int) data_get($location, 'id') === (int) $other['location']
                    );

                    $onlyOwnOrShared = $visible->every(
                        fn ($location): bool =>
                            data_get($location, 'is_shared') === true
                            || (int) data_get($location, 'id') === (int) $own['location']
                    );

                    return $containsOwn
                        && ! $containsOther
                        && $onlyOwnOrShared;
                })
        );

    $this->get(route('operator.operations.weather'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('operator/Operations')
                ->where('section', 'weather')
                ->has('dates', 1)
                ->where('boats', [])
        );

    $this->get(route('operator.operations.feedback'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('operator/Operations')
                ->where('section', 'feedback')
                ->has('feedback', 1)
                ->where('feedback.0.comment', 'Own private feedback')
                ->where('boats', [])
        );
});

/*
|--------------------------------------------------------------------------
| TEST 2 — PREVENT CROSS-OPERATOR CHANGES
|--------------------------------------------------------------------------
*/

test('other operator cannot change boat or weather for a different tenant', function () {
    $own = opsFixture('Owned');
    $other = opsFixture('Rival');

    $this->actingAs($own['admin'])
        ->patch(
            route(
                'operator.operations.boats.update',
                $other['package']
            ),
            [
                'boat_name' => 'Hijacked',
                'under_maintenance' => true,
            ]
        )
        ->assertNotFound();

    $this->post(
        route(
            'operator.operations.weather.toggle',
            $other['date']
        )
    )->assertNotFound();

    $this->post(
        route(
            'operator.operations.weather.refresh',
            $other['date']
        )
    )->assertNotFound();

    expect(
        (bool) DB::table('packages')
            ->where('id', $other['package'])
            ->value('under_maintenance')
    )->toBeFalse();

    expect(
        (bool) DB::table('tour_dates')
            ->where('id', $other['date'])
            ->value('travel_closed')
    )->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| TEST 3 — OWN BOAT AND WEATHER MANAGEMENT
|--------------------------------------------------------------------------
*/

test('own operator can toggle boat maintenance and travel closure', function () {
    $own = opsFixture('Local');

    $this->actingAs($own['admin'])
        ->patch(
            route(
                'operator.operations.boats.update',
                $own['package']
            ),
            [
                'boat_name' => 'New Local Boat',
                'under_maintenance' => true,
            ]
        )
        ->assertRedirect();

    $this->post(
        route(
            'operator.operations.weather.toggle',
            $own['date']
        )
    )->assertRedirect();

    $this->assertDatabaseHas('packages', [
        'id' => $own['package'],
        'boat_name' => 'New Local Boat',
        'under_maintenance' => 1,
    ]);

    $this->assertDatabaseHas('tour_dates', [
        'id' => $own['date'],
        'travel_closed' => 1,
    ]);
});

/*
|--------------------------------------------------------------------------
| TEST 4 — PICKUP LOCATION OWNERSHIP
|--------------------------------------------------------------------------
*/

test('operator can create own location but cannot edit or delete another tenant location', function () {
    $own = opsFixture('PiersOne');
    $other = opsFixture('PiersTwo');

    $this->actingAs($own['admin'])
        ->post(
            route('operator.operations.pickups.store'),
            [
                'name' =>
                    'Private New Pier '.Str::random(10),
                'status' => 'active',
            ]
        )
        ->assertRedirect();

    $created = DB::table('pickup_locations')
        ->where(
            'tour_operator_id',
            $own['operator']->id
        )
        ->orderByDesc('id')
        ->first();

    expect(
        (int) $created->tour_operator_id
    )->toBe(
        (int) $own['operator']->id
    );

    $this->patch(
        route(
            'operator.operations.pickups.update',
            $other['location']
        ),
        [
            'name' => 'Changed Foreign Pier',
            'status' => 'active',
        ]
    )->assertNotFound();

    $this->delete(
        route(
            'operator.operations.pickups.destroy',
            $other['location']
        )
    )->assertNotFound();

    $this->assertDatabaseHas(
        'pickup_locations',
        [
            'id' => $other['location'],
        ]
    );
});

/*
|--------------------------------------------------------------------------
| TEST 5 — PROTECT LOCATIONS USED IN SCHEDULES
|--------------------------------------------------------------------------
*/

test('locations used in pickup schedules cannot be deleted', function () {
    $own = opsFixture('UsedPier');

    $this->actingAs($own['admin'])
        ->delete(
            route(
                'operator.operations.pickups.destroy',
                $own['location']
            )
        )
        ->assertRedirect()
        ->assertSessionHasErrors('location');

    $this->assertDatabaseHas(
        'pickup_locations',
        [
            'id' => $own['location'],
        ]
    );
});

/*
|--------------------------------------------------------------------------
| TEST 6 — ISOLATED CSV REPORTS
|--------------------------------------------------------------------------
*/

test('csv report contains no other operator booking records', function () {
    $own = opsFixture('ExportOwn');
    $other = opsFixture('ExportOther');

    $response = $this
        ->actingAs($own['admin'])
        ->get(
            route(
                'operator.operations.report',
                [
                    'from' =>
                        today()->subDay()->toDateString(),
                    'to' =>
                        today()->addDay()->toDateString(),
                ]
            )
        );

    $response->assertOk();

    $csv = $response->streamedContent();

    expect($csv)
        ->toContain('ExportOwn tour')
        ->not->toContain('ExportOther tour')
        ->and($csv)
        ->toContain(
            (string) $own['booking']->id
        );
});

/*
|--------------------------------------------------------------------------
| TEST 7 — ROLE RESTRICTIONS
|--------------------------------------------------------------------------
*/

test('tourists and superadmins cannot access operator operations routes', function () {
    $own = opsFixture('Roles');

    $userRole = Role::query()->firstOrCreate([
        'name' => 'User',
    ]);

    $tourist = User::factory()->create([
        'role_id' => $userRole->id,
    ]);

    $superRole = Role::query()->firstOrCreate([
        'name' => 'Superadmin',
    ]);

    $super = User::factory()->create([
        'role_id' => $superRole->id,
    ]);

    foreach ([$tourist, $super] as $user) {
        $this->actingAs($user)
            ->get(route('operator.operations.index'))
            ->assertForbidden();
    }
});

/*
|--------------------------------------------------------------------------
| TEST 8 — BLOCK OLD SHARED ADMIN PAGES
|--------------------------------------------------------------------------
*/

test('old shared admin operations endpoints are forbidden to operators', function () {
    $own = opsFixture('LegacyDenied');

    $this->actingAs($own['admin']);

    foreach (
        [
            'admin.boat-maintenance.index',
            'admin.weather-advisories.index',
            'admin.pickup-locations.index',
            'admin.booking-feedback.index',
            'admin.reports.index',
        ] as $name
    ) {
        $this->get(route($name))
            ->assertForbidden();
    }
});
