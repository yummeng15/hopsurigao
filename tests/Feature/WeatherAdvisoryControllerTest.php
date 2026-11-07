<?php

use App\Models\Package;
use App\Models\Role;
use App\Models\TourDate;
use App\Models\User;
use App\Models\WeatherForecast;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('guests are redirected from weather advisories page', function () {
    $response = $this->get('/admin/weather-advisories');
    $response->assertRedirect('/login');
});

test('regular tourists cannot access admin weather advisories page', function () {
    $touristRole = Role::where('name', 'User')->first();
    $tourist = User::factory()->create(['role_id' => $touristRole->id]);

    $response = $this->actingAs($tourist)->get('/admin/weather-advisories');
    $response->assertForbidden();
});

test('operators and superadmins can access weather advisories page and data endpoint', function () {
    $operatorRole = Role::where('name', 'Admin')->first();
    $operator = User::factory()->create(['role_id' => $operatorRole->id]);

    $package = Package::create([
        'package_name' => 'Coron Expedition',
        'destination'  => 'Coron',
        'latitude'     => 11.9986,
        'longitude'    => 120.2043,
        'price'        => 2500,
        'status'       => 'active',
    ]);

    $tourDate = TourDate::create([
        'package_id' => $package->id,
        'tour_date'  => now()->addDays(5)->toDateString(),
        'capacity'   => 25,
    ]);

    WeatherForecast::create([
        'tour_date_id'         => $tourDate->id,
        'forecast_date'        => $tourDate->tour_date->toDateString(),
        'location'             => 'Coron',
        'latitude'             => 11.9986,
        'longitude'            => 120.2043,
        'temperature_max'      => 32.0,
        'temperature_min'      => 25.0,
        'weather_condition'    => 'Clear Sky',
        'weather_code'         => 0,
        'rainfall_probability' => 5,
        'wind_speed'           => 10.0,
        'advisory_level'       => 'green',
        'advisory_badge'       => 'Suitable',
        'advisory_message'     => 'Favorable conditions.',
        'is_outdated'          => false,
        'fetched_at'           => now(),
    ]);

    $pageResponse = $this->actingAs($operator)->get('/admin/weather-advisories');
    $pageResponse->assertOk();

    $dataResponse = $this->actingAs($operator)->getJson('/admin/weather-advisories/data');
    $dataResponse->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'tour_date_id',
                    'tour_date',
                    'package_name',
                    'destination',
                    'weather_condition',
                    'advisory_level',
                    'advisory_badge',
                ],
            ],
            'meta' => ['total', 'current_page'],
        ]);
});

