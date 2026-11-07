<?php

use App\Models\PickupLocation;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

test('staff can save the map pin for a pickup location', function () {
    $role = Role::query()->where('name', 'Superadmin')->first();

    expect($role)->not->toBeNull();

    $admin = User::factory()->create([
        'role_id' => $role->id,
    ]);

    $name = 'Map Pin Test Hub '.uniqid();

    $this->actingAs($admin)
        ->withSession(['_token' => 'test-token'])
        ->post(route('admin.pickup-locations.store'), [
            '_token' => 'test-token',
            'name' => $name,
            'address' => 'Cloud 9, General Luna',
            'description' => 'Pinned from the pickup map.',
            'status' => 'active',
            'latitude' => 9.8087,
            'longitude' => 126.168,
        ])
        ->assertRedirect(route('admin.pickup-locations.index'));

    $location = PickupLocation::query()->where('name', $name)->first();

    expect($location)->not->toBeNull()
        ->and(round((float) $location->latitude, 4))->toBe(9.8087)
        ->and(round((float) $location->longitude, 3))->toBe(126.168);
});

test('a pickup map pin must be a real coordinate', function () {
    $role = Role::query()->where('name', 'Superadmin')->first();

    $admin = User::factory()->create([
        'role_id' => $role->id,
    ]);

    $this->actingAs($admin)
        ->withSession(['_token' => 'test-token'])
        ->from(route('admin.pickup-locations.index'))
        ->post(route('admin.pickup-locations.store'), [
            '_token' => 'test-token',
            'name' => 'Invalid Map Pin '.uniqid(),
            'address' => 'Somewhere',
            'status' => 'active',
            'latitude' => 120,
            'longitude' => 126.1,
        ])
        ->assertSessionHasErrors('latitude');
});
