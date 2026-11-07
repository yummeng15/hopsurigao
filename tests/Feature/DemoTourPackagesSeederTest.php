<?php

use App\Models\Package;
use App\Models\PickupLocation;
use App\Models\Role;
use App\Models\TourDate;
use App\Models\TourOperator;
use App\Models\User;
use Database\Seeders\DemoTourPackagesSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    $this->withoutVite();
});

/**
 * @return array{operator: TourOperator}
 */
function demoSeederFixture(): array
{
    $operator = TourOperator::query()->create([
        'name' => 'Demo Defense '.Str::random(6),
        'slug' => 'demo-defense-'.Str::uuid(),
        'is_active' => true,
        'approval_status' => TourOperator::STATUS_ACTIVE,
    ]);

    User::factory()->create([
        'role_id' => Role::query()->firstOrCreate(['name' => 'Admin'])->id,
        'tour_operator_id' => $operator->id,
    ]);

    $sharedPickups = [
        ['name' => 'General Luna Public Market Pier', 'latitude' => 9.8032, 'longitude' => 126.1601],
        ['name' => 'General Luna Tourism Boulevard', 'latitude' => 9.8004, 'longitude' => 126.1622],
        ['name' => 'Dapa Port Departure Hub', 'latitude' => 9.7574, 'longitude' => 126.0528],
        ['name' => 'Del Carmen Municipal Tourism Center', 'latitude' => 9.8688, 'longitude' => 126.0264],
        ['name' => 'Surigao City Boulevard Wharf', 'latitude' => 9.7858, 'longitude' => 125.4942],
    ];

    foreach ($sharedPickups as $pickup) {
        PickupLocation::query()->firstOrCreate(
            [
                'name' => $pickup['name'],
                'tour_operator_id' => null,
            ],
            [
                'address' => $pickup['name'].' address',
                'latitude' => $pickup['latitude'],
                'longitude' => $pickup['longitude'],
                'status' => 'active',
            ]
        );
    }

    return compact('operator');
}

it('refuses to run without an explicit approved operator', function (): void {
    expect(fn () => (new DemoTourPackagesSeeder(null, false))->run())
        ->toThrow(RuntimeException::class);
});

it('seeds twelve publishable demo packages idempotently', function (): void {
    $fixture = demoSeederFixture();
    $operatorId = (int) $fixture['operator']->id;

    $first = new DemoTourPackagesSeeder($operatorId, false);
    $first->run();

    $packages = Package::withoutGlobalScopes()
        ->where('tour_operator_id', $operatorId)
        ->whereIn('package_name', [
            'Siargao Four-Island Adventure',
            'Mam-on and La Januza Island Escape',
            'Corregidor Island Nature Retreat',
            'Kawhagan and Pamomoan Beach Discovery',
            'Siargao Hidden Islands Expedition',
            'Daku Island Tropical Picnic',
            'Guyam and Naked Island Getaway',
            'San Benito Sandbar and Lake Adventure',
            'Basul Island Beach Escape',
            'Hikdop Island Cave and Beach Tour',
            'Danawan Island Discovery',
            'Sohoton Cave and Lagoon Expedition',
        ])
        ->get();

    expect($packages)->toHaveCount(12);
    expect($packages->every(fn (Package $package): bool => $package->status === 'active'))->toBeTrue();
    expect($packages->every(fn (Package $package): bool => (float) $package->price > 0))->toBeTrue();

    foreach ($packages as $package) {
        $futureReady = TourDate::withoutGlobalScopes()
            ->where('package_id', $package->id)
            ->whereDate('tour_date', '>=', today()->toDateString())
            ->where('travel_closed', false)
            ->whereHas('times')
            ->whereHas('pickupSchedules')
            ->exists();

        expect($futureReady)->toBeTrue();
        expect($package->inclusions()->count())->toBeGreaterThan(0);
        expect($package->activities()->count())->toBeGreaterThan(0);
        expect($package->destinations()->count())->toBeGreaterThan(0);
    }

    $priceSnapshot = $packages->pluck('price', 'package_name');

    $second = new DemoTourPackagesSeeder($operatorId, false);
    $second->run();

    $again = Package::withoutGlobalScopes()
        ->where('tour_operator_id', $operatorId)
        ->whereIn('package_name', $priceSnapshot->keys())
        ->get();

    expect($again)->toHaveCount(12);
    foreach ($again as $package) {
        expect((string) $package->price)->toBe((string) $priceSnapshot[$package->package_name]);
    }

    expect(
        Package::withoutGlobalScopes()
            ->where('tour_operator_id', $operatorId)
            ->whereIn('package_name', $priceSnapshot->keys())
            ->count()
    )->toBe(12);
});

it('exposes packages to the tourist marketplace after seeding', function (): void {
    $fixture = demoSeederFixture();
    (new DemoTourPackagesSeeder((int) $fixture['operator']->id, false))->run();

    $tourist = User::factory()->create([
        'role_id' => Role::query()->firstOrCreate(['name' => 'User'])->id,
    ]);

    $response = $this->actingAs($tourist)->get(route('user.packages.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('user/packages/Index')
        ->has('packages')
        ->where('packages', function ($packages): bool {
            return collect($packages)->contains(
                fn (array $package): bool => $package['package_name'] === 'Siargao Four-Island Adventure'
                    && $package['status'] === 'active'
            );
        }));
});
