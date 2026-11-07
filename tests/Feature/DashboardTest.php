<?php

use App\Models\Role;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users are redirected to their role-specific dashboard', function () {
    $userRole = Role::firstOrCreate(['name' => 'User']);
    $user = User::factory()->create([
        'role_id' => $userRole->id,
    ]);
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('user.dashboard'));
});