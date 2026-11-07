<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    $this->withoutVite();
    config()->set('inertia.ssr.enabled', false);
});

test('super admin can view packages but cannot edit or delete them', function () {
    $superadmin = User::factory()->create([
        'role_id' => Role::query()->firstOrCreate(['name' => 'Superadmin'])->id,
        'email_verified_at' => now(),
    ]);

    $packageId = DB::table('packages')->insertGetId([
        'package_name' => 'View Only Package',
        'destination' => 'Guyam Island',
        'price' => 1500,
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $page = file_get_contents(dirname(__DIR__, 3).'/resources/js/pages/admin/packages/Index.vue');

    expect($page)->toContain('View package')
        ->and($page)->not->toContain('New Package')
        ->and($page)->not->toContain('Edit package')
        ->and($page)->not->toContain('Delete package');

    $this->actingAs($superadmin)
        ->get(route('admin.packages.index'))
        ->assertOk();

    $this->actingAs($superadmin)
        ->get(route('admin.packages.create'))
        ->assertForbidden();

    $this->actingAs($superadmin)
        ->post(route('admin.packages.store'), [])
        ->assertForbidden();

    $this->actingAs($superadmin)
        ->get(route('admin.packages.edit', $packageId))
        ->assertForbidden();

    $this->actingAs($superadmin)
        ->delete(route('admin.packages.destroy', $packageId))
        ->assertForbidden();

    $this->assertDatabaseHas('packages', [
        'id' => $packageId,
        'package_name' => 'View Only Package',
    ]);
});
