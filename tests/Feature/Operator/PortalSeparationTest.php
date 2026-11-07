
<?php

use App\Models\Role;
use App\Models\TourOperator;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

// Roll back test-created records, but still use a dedicated testing database.
uses(DatabaseTransactions::class);

function createPortalOperator(bool $active = true): TourOperator
{
    return TourOperator::create([
        'name' => 'Test Island Hopping Operator',
        'slug' => 'test-operator-'.Str::uuid(),
        'description' => 'Operator created for automated tests.',
        'contact_email' => 'operator-test@example.test',
        'is_active' => $active,
    ]);
}

function portalUser(string $roleName, ?TourOperator $operator = null): User
{
    $role = Role::firstOrCreate(['name' => $roleName]);

    $user = User::factory()->create([
        'role_id' => $role->id,
    ]);

    // tour_operator_id is not in User::$fillable. Assign explicitly here;
    // do not add it to mass-assignable fields just for this test.
    if ($operator !== null) {
        $user->forceFill([
            'tour_operator_id' => $operator->id,
        ])->save();
    }

    return $user->refresh();
}

test('assigned active operator can open their own dashboard', function () {
    $operator = createPortalOperator();
    $admin = portalUser('Admin', $operator);

    expect($admin->tourOperator?->id)->toBe($operator->id);
    expect($admin->tourOperator?->is_active)->toBeTrue();

    $this->actingAs($admin, 'web')
        ->get(route('operator.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('operator/Dashboard')
            ->has('stats')
            ->has('todayBookings'));
});

test('assigned operator is redirected to operator dashboard', function () {
    $admin = portalUser('Admin', createPortalOperator());

    $this->actingAs($admin, 'web')
        ->get(route('dashboard'))
        ->assertRedirect(route('operator.dashboard'));
});

test('unassigned admin cannot open operator dashboard', function () {
    $admin = portalUser('Admin');

    $this->actingAs($admin, 'web')
        ->get(route('operator.dashboard'))
        ->assertForbidden();
});

test('inactive operator cannot open operator dashboard', function () {
    $admin = portalUser('Admin', createPortalOperator(false));

    $this->actingAs($admin, 'web')
        ->get(route('operator.dashboard'))
        ->assertForbidden();
});

test('super admin cannot open operator dashboard', function () {
    $superadmin = portalUser('Superadmin');

    $this->actingAs($superadmin, 'web')
        ->get(route('operator.dashboard'))
        ->assertForbidden();
});

test('tourist cannot open operator dashboard', function () {
    $tourist = portalUser('User');

    $this->actingAs($tourist, 'web')
        ->get(route('operator.dashboard'))
        ->assertForbidden();
});

test('super admin cannot open tourist dashboard', function () {
    $superadmin = portalUser('Superadmin');

    $this->actingAs($superadmin, 'web')
        ->get(route('user.dashboard'))
        ->assertForbidden();
});
