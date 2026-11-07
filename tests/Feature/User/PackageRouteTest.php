<?php

use App\Models\Package;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

test('a guest sees how many islands the package visits and the stop order', function () {
    $role = Role::query()->where('name', 'User')->first();
    $tourist = User::factory()->create([
        'role_id' => $role->id,
    ]);

    $package = Package::query()->where('status', 'active')->first();

    expect($package)->not->toBeNull();

    $package->destinations()->delete();
    $package->destinations()->createMany([
        ['name' => 'Daku Island', 'position' => 0],
        ['name' => 'Guyam Island', 'position' => 1],
        ['name' => 'Naked Island', 'position' => 2],
    ]);

    $package->activities()->delete();
    $package->activities()->create([
        'description' => 'Swimming and beach time',
    ]);

    $this->actingAs($tourist)
        ->get(route('user.packages.show', $package))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('package.island_count', 3)
            ->where('package.stops.0', 'Daku Island')
            ->where('package.stops.1', 'Guyam Island')
            ->where('package.stops.2', 'Naked Island')
            ->where('package.activities.0.description', 'Swimming and beach time')
        );

    $root = dirname(__DIR__, 3);
    $page = file_get_contents($root.'/resources/js/pages/user/packages/Show.vue');
    $booking = file_get_contents($root.'/resources/js/pages/user/bookings/Show.vue');
    $route = file_get_contents($root.'/resources/js/components/tours/TourRoute.vue');

    expect($page)->toContain('Destinations to visit')
        ->and($page)->toContain('Activities')
        ->and($page)->toContain('Available')
        ->and($page)->toContain('boat_name')
        ->and($page)->toContain('TourRoute')
        ->and($booking)->toContain('TourRoute')
        ->and($route)->toContain('First stop');
});
