
<?php

use App\Models\Role;
use App\Models\TourOperator;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(DatabaseTransactions::class);

function stageThreeOperator(string $name, bool $active = true): TourOperator
{
    return TourOperator::create([
        'name' => $name,
        'slug' => 'test-'.Str::uuid(),
        'is_active' => $active,
    ]);
}

function stageThreeUser(string $roleName, ?TourOperator $operator = null): User
{
    $role = Role::firstOrCreate(['name' => $roleName]);
    $user = User::factory()->create(['role_id' => $role->id]);

    if ($operator !== null) {
        $user->forceFill(['tour_operator_id' => $operator->id])->save();
    }

    return $user->refresh();
}

function stageThreePackage(TourOperator $operator, string $name): int
{
    // Use a direct insert for these isolated read-only listing tests.
    // No package-management model events or upload side effects are needed.
    return DB::table('packages')->insertGetId([
        'tour_operator_id' => $operator->id,
        'package_name' => $name,
        'destination' => 'Sample destination',
        'price' => 1500,
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

test('assigned operator sees only own packages', function () {
    $operatorA = stageThreeOperator('Operator A');
    $operatorB = stageThreeOperator('Operator B');
    $adminA = stageThreeUser('Admin', $operatorA);
    stageThreePackage($operatorA, 'A Exclusive Tour');
    stageThreePackage($operatorB, 'B Private Tour');

    $this->actingAs($adminA, 'web')
        ->get(route('operator.packages.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('operator/Packages')
            ->where('operatorName', 'Operator A')
            ->where('packages.total', 1)
            ->where('packages.data.0.package_name', 'A Exclusive Tour')
            ->missing('packages.data.1'));
});

test('unassigned operator is denied access to packages', function () {
    $this->actingAs(stageThreeUser('Admin'), 'web')
        ->get(route('operator.packages.index'))
        ->assertForbidden();
});

test('inactive operator is denied access to packages', function () {
    $this->actingAs(stageThreeUser('Admin', stageThreeOperator('Inactive', false)), 'web')
        ->get(route('operator.packages.index'))
        ->assertForbidden();
});

test('super admin and tourist cannot open operator packages', function () {
    foreach (['Superadmin', 'User'] as $role) {
        $this->actingAs(stageThreeUser($role), 'web')
            ->get(route('operator.packages.index'))
            ->assertForbidden();
    }
});
