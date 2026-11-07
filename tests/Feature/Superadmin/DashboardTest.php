<?php

use App\Models\Role;
use App\Models\User;

test('superadmin can access superadmin dashboard with full statistics, charts, and tables', function () {
    $superadminRole = Role::firstOrCreate(['name' => 'Superadmin']);
    $superadmin = User::factory()->create([
        'role_id' => $superadminRole->id,
    ]);

    $response = $this->actingAs($superadmin)->get(route('superadmin.dashboard'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('superadmin/Dashboard')
        ->has('stats')
        ->has('monthlyTrends')
        ->has('popularPackages')
        ->has('statusDistribution')
        ->has('weatherDistribution')
        ->has('recentBookings')
        ->has('upcomingTours')
        ->has('recentUsers')
        ->has('roleSummary')
    );
});

test('regular user cannot access superadmin dashboard', function () {
    $userRole = Role::firstOrCreate(['name' => 'User']);
    $user = User::factory()->create([
        'role_id' => $userRole->id,
    ]);

    $response = $this->actingAs($user)->get(route('superadmin.dashboard'));

    $response->assertForbidden();
});
