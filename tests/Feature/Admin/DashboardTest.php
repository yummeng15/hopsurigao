<?php

use App\Models\Role;
use App\Models\User;

test('admin dashboard route redirects to superadmin dashboard', function () {
    $superadminRole = Role::firstOrCreate(['name' => 'Superadmin']);
    $superadmin = User::factory()->create([
        'role_id' => $superadminRole->id,
    ]);

    $response = $this->actingAs($superadmin)->get(route('admin.dashboard'));

    $response->assertRedirect(route('superadmin.dashboard'));
});
