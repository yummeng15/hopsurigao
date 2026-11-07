
<?php

use App\Models\Role;
use App\Models\TourOperator;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(DatabaseTransactions::class);

function stageFourOperator(string $name): TourOperator
{
    return TourOperator::create([
        'name' => $name,
        'slug' => 'stage-four-'.Str::uuid(),
        'is_active' => true,
    ]);
}

function stageFourUser(string $role, ?TourOperator $operator = null): User
{
    $roleRow = Role::firstOrCreate(['name' => $role]);

    $user = User::factory()->create([
        'role_id' => $roleRow->id,
    ]);

    if ($operator) {
        $user->forceFill([
            'tour_operator_id' => $operator->id,
        ])->save();
    }

    return $user->refresh();
}

function stageFourPackage(TourOperator $operator): int
{
    return DB::table('packages')->insertGetId([
        'tour_operator_id' => $operator->id,
        'package_name' => 'Existing Island Tour',
        'boat_name' => 'Island Boat',
        'destination' => 'Daku Island',
        'price' => '1000.00',
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

test('operator can open create form', function () {
    $operator = stageFourOperator('Operator A');
    $admin = stageFourUser('Admin', $operator);

    $this->actingAs($admin, 'web')
        ->get(route('operator.packages.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('operator/PackageForm')
            ->where('mode', 'create'));
});

test('operator creates an inactive package belonging only to their company', function () {
    $operatorA = stageFourOperator('Operator A');
    $operatorB = stageFourOperator('Operator B');
    $admin = stageFourUser('Admin', $operatorA);

    $this->actingAs($admin, 'web')
        ->post(route('operator.packages.store'), [
            'package_name' => 'New Three Island Tour',
            'boat_name' => 'Boat A',
            'destinations_text' => "Daku Island\nGuyam Island",
            'description' => 'An island tour',
            'price' => '1500.00',

            // These client-supplied values must be ignored.
            'status' => 'active',
            'tour_operator_id' => $operatorB->id,
        ])
        ->assertRedirect(route('operator.packages.index'));

    $package = DB::table('packages')
        ->where('package_name', 'New Three Island Tour')
        ->first();

    expect($package)->not->toBeNull();
    expect((int) $package->tour_operator_id)->toBe($operatorA->id);
    expect($package->status)->toBe('inactive');

    $names = DB::table('package_destinations')
        ->where('package_id', $package->id)
        ->orderBy('position')
        ->pluck('name')
        ->all();

    expect($names)->toBe([
        'Daku Island',
        'Guyam Island',
    ]);
});

test('operator can edit their own basic package details without changing status', function () {
    $operator = stageFourOperator('Operator A');
    $admin = stageFourUser('Admin', $operator);
    $packageId = stageFourPackage($operator);

    $this->actingAs($admin, 'web')
        ->put(route('operator.packages.update', $packageId), [
            'package_name' => 'Updated Island Tour',
            'boat_name' => 'Island Boat',
            'destinations_text' => 'Daku Island',
            'description' => 'Updated description',
            'price' => '1000.00',
            'status' => 'inactive',
        ])
        ->assertRedirect(route('operator.packages.index'));

    $this->assertDatabaseHas('packages', [
        'id' => $packageId,
        'tour_operator_id' => $operator->id,
        'package_name' => 'Updated Island Tour',
        'status' => 'active',
    ]);
});

test('operator cannot see or edit another company package', function () {
    $operatorA = stageFourOperator('Operator A');
    $operatorB = stageFourOperator('Operator B');
    $admin = stageFourUser('Admin', $operatorA);

    $foreignId = stageFourPackage($operatorB);

    $this->actingAs($admin, 'web')
        ->get(route('operator.packages.edit', $foreignId))
        ->assertNotFound();

    $this->put(route('operator.packages.update', $foreignId), [
        'package_name' => 'Hijacked',
        'boat_name' => 'Boat A',
        'destinations_text' => 'Daku Island',
        'price' => 1000,
    ])->assertNotFound();

    $this->assertDatabaseHas('packages', [
        'id' => $foreignId,
        'package_name' => 'Existing Island Tour',
        'tour_operator_id' => $operatorB->id,
    ]);
});

test('operator cannot bypass the dedicated portal through legacy package routes', function () {
    $admin = stageFourUser('Admin', stageFourOperator('Operator A'));

    $this->actingAs($admin, 'web')
        ->get(route('admin.packages.index'))
        ->assertForbidden();

    $this->get(route('admin.packages.create'))
        ->assertForbidden();
});

test('tourist and super admin cannot write through operator package routes', function () {
    foreach (['User', 'Superadmin'] as $role) {
        $this->actingAs(stageFourUser($role), 'web')
            ->get(route('operator.packages.create'))
            ->assertForbidden();

        $this->post(route('operator.packages.store'), [])
            ->assertForbidden();
    }
});
