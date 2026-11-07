<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

beforeEach(function () {
    config()->set('inertia.ssr.enabled', false);
});

test('the administrator cannot delete a user', function () {
    $adminRole = Role::query()->firstOrCreate(['name' => 'Superadmin']);
    $touristRole = Role::query()->firstOrCreate(['name' => 'User']);

    $admin = User::factory()->create([
        'role_id' => $adminRole->id,
    ]);

    $tourist = User::factory()->create([
        'role_id' => $touristRole->id,
    ]);

    $this->actingAs($admin)
        ->withSession(['_token' => 'test-token'])
        ->delete(route('admin.users.destroy', $tourist), [
            '_token' => 'test-token',
        ])
        ->assertForbidden();

    expect(User::query()->whereKey($tourist->id)->exists())->toBeTrue();

    $page = file_get_contents(dirname(__DIR__, 3).'/resources/js/pages/admin/users/Index.vue');

    expect($page)->not->toContain('Delete user')
        ->and($page)->not->toContain('Trash2');
});

test('the administrator cannot edit a user', function () {
    $adminRole = Role::query()->firstOrCreate(['name' => 'Superadmin']);
    $touristRole = Role::query()->firstOrCreate(['name' => 'User']);

    $admin = User::factory()->create([
        'role_id' => $adminRole->id,
    ]);

    $tourist = User::factory()->create([
        'role_id' => $touristRole->id,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.users.edit', $tourist))
        ->assertForbidden();

    $this->actingAs($admin)
        ->withSession(['_token' => 'test-token'])
        ->put(route('admin.users.update', $tourist), [
            '_token' => 'test-token',
            'name' => 'Changed Name',
            'email' => $tourist->email,
            'role_id' => $tourist->role_id,
        ])
        ->assertForbidden();

    expect($tourist->fresh()->name)->not->toBe('Changed Name');

    $page = file_get_contents(dirname(__DIR__, 3).'/resources/js/pages/admin/users/Index.vue');

    expect($page)->not->toContain('Edit user')
        ->and($page)->not->toContain('Pencil');
});

test('the administrator cannot create a user', function () {
    $adminRole = Role::query()->firstOrCreate(['name' => 'Superadmin']);
    $touristRole = Role::query()->firstOrCreate(['name' => 'User']);

    $admin = User::factory()->create([
        'role_id' => $adminRole->id,
    ]);

    $before = User::query()->count();

    $this->actingAs($admin)
        ->get(route('admin.users.create'))
        ->assertForbidden();

    $this->actingAs($admin)
        ->withSession(['_token' => 'test-token'])
        ->post(route('admin.users.store'), [
            '_token' => 'test-token',
            'name' => 'Blocked Tourist',
            'email' => 'blocked-tourist-'.uniqid().'@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role_id' => $touristRole->id,
        ])
        ->assertForbidden();

    expect(User::query()->count())->toBe($before);

    $page = file_get_contents(dirname(__DIR__, 3).'/resources/js/pages/admin/users/Index.vue');

    expect($page)->not->toContain('New User')
        ->and($page)->not->toContain('create()');
});
