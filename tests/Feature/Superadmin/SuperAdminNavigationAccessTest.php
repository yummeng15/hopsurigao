
<?php

use App\Models\Role;
use App\Models\TourOperator;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    $this->withoutVite();
    config()->set('inertia.ssr.enabled', false);
});

/**
 * Create a user with the specified HopSurigao role.
 */
function fixTwoAccount(
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

/**
 * Create an active business for an Operator account.
 */
function fixTwoOperator(): TourOperator
{
    return TourOperator::create([
        'name' => 'Fix Two Test Operator',
        'slug' => 'fix-two-'.Str::uuid(),
        'is_active' => true,
    ]);
}

/*
|--------------------------------------------------------------------------
| TEST 1 - SUPER ADMIN MANAGEMENT PAGES
|--------------------------------------------------------------------------
*/

test('super admin can open the operator management pages', function () {
    // The Admin role must exist for the Operator Admin listing.
    Role::firstOrCreate([
        'name' => 'Admin',
    ]);

    $superadmin = fixTwoAccount('Superadmin');

    $this->actingAs($superadmin, 'web');

    $this->get(
        route('superadmin.operators.index')
    )
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('superadmin/operators/Index')
            ->has('operators')
            ->has('availableAdmins')
            ->has('availablePackages')
        );

    $this->get(
        route('superadmin.operator-admins.index')
    )
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('superadmin/operator-admins/Index')
            ->has('operators')
            ->has('admins')
        );

    $this->get(
        route('superadmin.operator-finance.index')
    )
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('superadmin/OperatorFinance')
            ->has('operators')
            ->has('totals')
        );
});

/*
|--------------------------------------------------------------------------
| TEST 2 - OPERATOR ACCESS RESTRICTIONS
|--------------------------------------------------------------------------
*/

test('tour operator cannot open super admin management pages', function () {
    $operator = fixTwoOperator();

    $admin = fixTwoAccount(
        'Admin',
        $operator
    );

    $this->actingAs($admin, 'web');

    $restrictedRoutes = [
        'superadmin.dashboard',
        'superadmin.operators.index',
        'superadmin.operator-admins.index',
        'superadmin.operator-finance.index',
        'superadmin.documentation',
        'superadmin.settings.edit',
    ];

    foreach ($restrictedRoutes as $name) {
        $this->get(
            route($name)
        )->assertForbidden();
    }

    // The Operator's own dashboard must remain accessible.
    $this->get(
        route('operator.dashboard')
    )->assertOk();
});

/*
|--------------------------------------------------------------------------
| TEST 3 - TOURIST ACCESS RESTRICTIONS
|--------------------------------------------------------------------------
*/

test('tourist cannot open super admin management pages', function () {
    $tourist = fixTwoAccount('User');

    $this->actingAs($tourist, 'web');

    $restrictedRoutes = [
        'superadmin.dashboard',
        'superadmin.operators.index',
        'superadmin.operator-admins.index',
        'superadmin.operator-finance.index',
        'superadmin.documentation',
        'superadmin.settings.edit',
    ];

    foreach ($restrictedRoutes as $name) {
        $this->get(
            route($name)
        )->assertForbidden();
    }

    // The Tourist still has their own separate dashboard.
    $this->get(
        route('user.dashboard')
    )->assertOk();
});

/*
|--------------------------------------------------------------------------
| TEST 4 - PROTECT MANAGEMENT WRITE ACTIONS
|--------------------------------------------------------------------------
*/

test('tourist and operator cannot create tour operators or admin accounts', function () {
    $accounts = [
        fixTwoAccount('User'),
        fixTwoAccount(
            'Admin',
            fixTwoOperator()
        ),
    ];

    foreach ($accounts as $account) {
        $this->actingAs($account, 'web');

        $this->post(
            route('superadmin.operators.store'),
            []
        )->assertForbidden();

        $this->post(
            route('superadmin.operator-admins.store'),
            []
        )->assertForbidden();
    }
});

/*
|--------------------------------------------------------------------------
| TEST 5 - VERIFY THE SUPER ADMIN SIDEBAR LINKS
|--------------------------------------------------------------------------
*/

test('super admin navigation includes its management and finance links', function () {
    $path = resource_path(
        'js/navigation/admin.ts'
    );

    expect(File::exists($path))->toBeTrue();

    $navigation = File::get($path);

    $expectedLinks = [
        '/superadmin/dashboard',
        '/superadmin/operators',
        '/superadmin/operator-finance',
        '/superadmin/settings',
        '/admin/users',
        '/admin/packages',
        '/admin/bookings',
        '/admin/payments',
        '/admin/reports',
    ];

    foreach ($expectedLinks as $url) {
        expect($navigation)->toContain(
            "href: '".$url."'"
        );
    }

    // Super Admin navigation should not contain
    // daily Operator-only or Tourist-only routes.
    // Operator Admins stay off the sidebar; registration is self-serve.
    expect($navigation)
        ->not->toContain("href: '/superadmin/operator-admins'")
        ->not->toContain("href: '/operator/")
        ->not->toContain("href: '/user/")
        ->not->toContain("href: '/operator/walk-ins/create'")
        ->not->toContain("href: '/operator/qr-scanner'")
        ->not->toContain("href: '/superadmin/documentation'");
});
