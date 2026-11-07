<?php

use App\Models\Package;
use App\Models\Role;
use App\Models\TourOperator;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    config()->set('inertia.ssr.enabled', false);
});

test('super admin package oversight cannot create packages', function () {
    $admin = User::factory()->create([
        'role_id' => Role::query()->firstOrCreate(['name' => 'Superadmin'])->id,
    ]);

    $before = Package::query()->count();

    $this->actingAs($admin)
        ->get(route('admin.packages.create'))
        ->assertForbidden();

    $this->actingAs($admin)
        ->withSession(['_token' => 'test-token'])
        ->post(route('admin.packages.store'), [
            '_token' => 'test-token',
            'package_name' => 'Blocked Package '.uniqid(),
            'destinations' => ['Daku Island'],
            'price' => 1500,
            'status' => 'active',
        ])
        ->assertForbidden();

    expect(Package::query()->count())->toBe($before);
});

test('operator can save multiple destinations on a package', function () {
    $operator = TourOperator::create([
        'name' => 'Destination Operator',
        'slug' => 'destination-op-'.Str::uuid(),
        'is_active' => true,
        'approval_status' => 'active',
    ]);

    $admin = User::factory()->create([
        'role_id' => Role::query()->firstOrCreate(['name' => 'Admin'])->id,
        'tour_operator_id' => $operator->id,
    ]);

    $name = 'Multi Destination Package '.uniqid();

    $this->actingAs($admin)
        ->post(route('operator.packages.store'), [
            'package_name' => $name,
            'boat_name' => 'Daku Bangka',
            'destinations_text' => "Daku Island\nGuyam Island\nNaked Island",
            'description' => 'Island hopping with several stops.',
            'price' => '1500.00',
        ])
        ->assertRedirect(route('operator.packages.index'));

    $package = Package::query()->where('package_name', $name)->first();

    expect($package)->not->toBeNull()
        ->and($package->destination)->toBe('Daku Island, Guyam Island, Naked Island')
        ->and($package->destinations()->orderBy('position')->pluck('name')->all())->toBe([
            'Daku Island',
            'Guyam Island',
            'Naked Island',
        ]);
});

test('a package needs at least one destination', function () {
    $operator = TourOperator::create([
        'name' => 'Empty Destination Operator',
        'slug' => 'empty-dest-op-'.Str::uuid(),
        'is_active' => true,
        'approval_status' => 'active',
    ]);

    $admin = User::factory()->create([
        'role_id' => Role::query()->firstOrCreate(['name' => 'Admin'])->id,
        'tour_operator_id' => $operator->id,
    ]);

    $this->actingAs($admin)
        ->from(route('operator.packages.create'))
        ->post(route('operator.packages.store'), [
            'package_name' => 'No Destination '.uniqid(),
            'destinations_text' => '',
            'price' => '1500.00',
        ])
        ->assertSessionHasErrors('destinations_text');
});
