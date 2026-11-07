<?php

use App\Models\Package;
use App\Models\PickupLocation;
use Database\Seeders\PackageSeeder;
use Database\Seeders\PickupLocationSeeder;
use Database\Seeders\SurigaoDelNorteTourDateSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

test('surigao del norte packages can be booked from surigao city', function () {
    $this->seed(PickupLocationSeeder::class);
    $this->seed(PackageSeeder::class);
    $this->seed(SurigaoDelNorteTourDateSeeder::class);

    $package = Package::query()
        ->where('package_name', 'Surigao City Island Hopping')
        ->first();

    expect($package)->not->toBeNull()
        ->and($package->status)->toBe('active')
        ->and($package->description)->toContain('Surigao del Norte')
        ->and($package->destination)->toBe('Basul Island, Hikdop Island, Buenavista Cave')
        ->and($package->destinations()->orderBy('position')->pluck('name')->all())->toBe([
            'Basul Island',
            'Hikdop Island',
            'Buenavista Cave',
        ]);

    $cave = Package::query()->where('package_name', 'Silop Cave Explorer')->first();

    expect(Package::query()->where('package_name', 'Day-asin & Mabua Coastal Tour')->exists())->toBeFalse()
        ->and(PickupLocation::query()->where('name', 'Day-asin Wharf')->exists())->toBeFalse()
        ->and($cave)->not->toBeNull()
        ->and($cave->description)->toContain('Surigao del Norte');

    $tourDate = $package->tourDates()
        ->whereDate('tour_date', '>=', now()->toDateString())
        ->first();

    expect($tourDate)->not->toBeNull();

    $pickupNames = $tourDate->pickupSchedules()
        ->with('pickupLocation')
        ->get()
        ->pluck('pickupLocation.name')
        ->all();

    expect($pickupNames)->toContain('Surigao City Boulevard Wharf')
        ->and($pickupNames)->not->toContain('Dapa Port Departure Hub');
});
