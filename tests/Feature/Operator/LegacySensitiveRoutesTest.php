
<?php

use App\Models\Role;
use App\Models\TourOperator;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    $this->withoutVite();
});

/**
 * Create an isolated operator for security testing.
 */
function fixOneOperator(bool $active = true): TourOperator
{
    return TourOperator::create([
        'name' => 'Security Test Operator',
        'slug' => 'fix-one-'.Str::uuid(),
        'is_active' => $active,
    ]);
}

/**
 * Create a user with a specific role and operator.
 */
function fixOneAccount(
    string $roleName,
    ?TourOperator $operator = null
): User {
    $role = Role::firstOrCreate([
        'name' => $roleName,
    ]);

    $user = User::factory()->create([
        'role_id' => $role->id,
    ]);

    if ($operator !== null) {
        $user->forceFill([
            'tour_operator_id' => $operator->id,
        ])->save();
    }

    return $user->refresh();
}

/*
|--------------------------------------------------------------------------
| TEST 1: OPERATOR REPORT ACCESS
|--------------------------------------------------------------------------
*/

test('operator cannot open shared platform reports', function () {
    $operator = fixOneOperator();

    $admin = fixOneAccount(
        'Admin',
        $operator
    );

    $this->actingAs($admin, 'web')
        ->get(route('admin.reports.index'))
        ->assertForbidden();

    $this->get(
        route('admin.reports.preview')
    )->assertForbidden();

    $this->get(
        route('admin.reports.download')
    )->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| TEST 2: GLOBAL WEATHER REFRESH
|--------------------------------------------------------------------------
*/

test('operator cannot trigger global weather refresh', function () {
    $operator = fixOneOperator();

    $admin = fixOneAccount(
        'Admin',
        $operator
    );

    $this->actingAs($admin, 'web')
        ->post(
            route('admin.weather-advisories.refresh-all')
        )
        ->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| TEST 3: LEGACY ADMIN DASHBOARD
|--------------------------------------------------------------------------
*/

test('operator legacy dashboard redirects to operator dashboard', function () {
    $operator = fixOneOperator();

    $admin = fixOneAccount(
        'Admin',
        $operator
    );

    $this->actingAs($admin, 'web')
        ->get(route('admin.dashboard'))
        ->assertRedirect(
            route('operator.dashboard')
        );
});

/*
|--------------------------------------------------------------------------
| TEST 4: OLD PAYMENT ENDPOINTS
|--------------------------------------------------------------------------
*/

test('operator cannot access shared payment management', function () {
    $operator = fixOneOperator();

    $admin = fixOneAccount(
        'Admin',
        $operator
    );

    $this->actingAs($admin, 'web')
        ->get(route('admin.payments.index'))
        ->assertForbidden();

    $this->get(
        route('admin.payments.data')
    )->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| TEST 5: OLD BOOKING ENDPOINTS
|--------------------------------------------------------------------------
*/

test('operator cannot access shared booking management', function () {
    $operator = fixOneOperator();

    $admin = fixOneAccount(
        'Admin',
        $operator
    );

    $this->actingAs($admin, 'web')
        ->get(route('admin.bookings.index'))
        ->assertForbidden();

    $this->get(
        route('admin.bookings.data')
    )->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| TEST 6: INACTIVE OPERATOR
|--------------------------------------------------------------------------
*/

test('inactive operator cannot access the legacy admin dashboard', function () {
    $operator = fixOneOperator(false);

    $admin = fixOneAccount(
        'Admin',
        $operator
    );

    $this->actingAs($admin, 'web')
        ->get(route('admin.dashboard'))
        ->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| TEST 7: UNASSIGNED OPERATOR
|--------------------------------------------------------------------------
*/

test('unassigned operator cannot access legacy admin routes', function () {
    $admin = fixOneAccount('Admin');

    $this->actingAs($admin, 'web')
        ->get(route('admin.dashboard'))
        ->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| TEST 8: TOURIST ACCESS
|--------------------------------------------------------------------------
*/

test('tourist cannot access legacy admin reports', function () {
    $tourist = fixOneAccount('User');

    $this->actingAs($tourist, 'web')
        ->get(route('admin.reports.index'))
        ->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| TEST 9: SUPER ADMIN REDIRECT
|--------------------------------------------------------------------------
*/

test('super admin keeps their own dashboard redirect', function () {
    $superadmin = fixOneAccount('Superadmin');

    $this->actingAs($superadmin, 'web')
        ->get(route('admin.dashboard'))
        ->assertRedirect(
            route('superadmin.dashboard')
        );
});
