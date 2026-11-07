<?php

use App\Models\Role;
use App\Models\TourOperator;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;

uses(DatabaseTransactions::class);

function legacyRoleUser(string $roleName): User
{
    $role = Role::firstOrCreate(['name' => $roleName]);
    $user = User::factory()->create(['role_id' => $role->id]);

    if ($roleName === 'Admin') {
        $operator = TourOperator::create([
            'name' => 'Security Test Operator',
            'slug' => 'security-test-'.Str::uuid(),
            'is_active' => true,
        ]);

        $user->forceFill(['tour_operator_id' => $operator->id])->save();
    }

    return $user->refresh();
}

test('an assigned operator cannot open account management', function () {
    $operator = legacyRoleUser('Admin');

    $this->actingAs($operator, 'web');

    foreach (['admin.users.index', 'admin.roles.index', 'admin.permissions.index'] as $name) {
        $this->get(route($name))->assertForbidden();
    }
});

test('an assigned operator cannot open refund management', function () {
    $operator = legacyRoleUser('Admin');

    $this->actingAs($operator, 'web')
        ->get(route('admin.refunds.index'))
        ->assertForbidden();
});

test('an assigned operator cannot access destructive payment endpoints', function () {
    $operator = legacyRoleUser('Admin');

    $this->actingAs($operator, 'web')
        ->get(route('admin.payments.create'))
        ->assertForbidden();
});

test('tourists cannot use legacy administrative routes', function () {
    $tourist = legacyRoleUser('User');

    $this->actingAs($tourist, 'web')
        ->get(route('admin.bookings.index'))
        ->assertForbidden();
});