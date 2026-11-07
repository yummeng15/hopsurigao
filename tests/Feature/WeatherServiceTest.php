<?php

use App\Models\Package;
use App\Models\TourDate;
use App\Models\WeatherForecast;
use App\Services\WeatherAdvisoryService;
use App\Services\WeatherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('weather service fetches and stores forecast using mock API response', function () {
    $package = Package::create([
        'package_name' => 'Siargao Island Hopping',
        'destination'  => 'Siargao',
        'latitude'     => 9.8583,
        'longitude'    => 126.0461,
        'price'        => 1500,
        'status'       => 'active',
    ]);

    $tourDate = TourDate::create([
        'package_id' => $package->id,
        'tour_date'  => now()->addDays(2)->toDateString(),
        'capacity'   => 20,
    ]);

    $targetDateStr = $tourDate->tour_date->toDateString();

    Http::fake([
        'https://api.open-meteo.com/v1/forecast*' => Http::response([
            'daily' => [
                'time'                          => [$targetDateStr],
                'weather_code'                  => [0],
                'temperature_2m_max'            => [31.5],
                'temperature_2m_min'            => [24.0],
                'precipitation_sum'             => [0.0],
                'precipitation_probability_max' => [10],
                'wind_speed_10m_max'            => [14.0],
                'wind_gusts_10m_max'            => [20.0],
                'wind_direction_10m_dominant'   => [90],
            ],
            'current' => [
                'temperature_2m'       => 28.5,
                'relative_humidity_2m' => 65,
                'weather_code'         => 0,
                'wind_speed_10m'       => 12.0,
            ],
        ], 200),
    ]);

    $service = new WeatherService(new WeatherAdvisoryService());
    $forecast = $service->getForecastForTourDate($tourDate, forceFresh: true);

    expect($forecast)->toBeInstanceOf(WeatherForecast::class)
        ->and($forecast->tour_date_id)->toBe($tourDate->id)
        ->and($forecast->advisory_level)->toBe('green')
        ->and($forecast->advisory_badge)->toBe('Suitable')
        ->and($forecast->location)->toBe('Siargao')
        ->and((float) $forecast->temperature_max)->toBe(31.5)
        ->and($forecast->rainfall_probability)->toBe(10);

    // Database record was persisted
    $this->assertDatabaseHas('weather_forecasts', [
        'tour_date_id'  => $tourDate->id,
        'forecast_date' => $targetDateStr,
        'advisory_level' => 'green',
    ]);
});

test('weather service handles API failures gracefully by returning cached or existing fallback', function () {
    $package = Package::create([
        'package_name' => 'Camotes Tour',
        'destination'  => 'Camotes Islands',
        'latitude'     => 10.6698,
        'longitude'    => 124.3822,
        'price'        => 1200,
        'status'       => 'active',
    ]);

    $tourDate = TourDate::create([
        'package_id' => $package->id,
        'tour_date'  => now()->addDays(3)->toDateString(),
        'capacity'   => 15,
    ]);

    // Pre-existing stored record
    $existing = WeatherForecast::create([
        'tour_date_id'         => $tourDate->id,
        'forecast_date'        => $tourDate->tour_date->toDateString(),
        'location'             => 'Camotes Islands',
        'latitude'             => 10.6698,
        'longitude'            => 124.3822,
        'temperature_max'      => 30.0,
        'temperature_min'      => 24.0,
        'weather_condition'    => 'Partly Cloudy',
        'weather_code'         => 2,
        'rainfall_probability' => 20,
        'wind_speed'           => 15.0,
        'advisory_level'       => 'green',
        'advisory_badge'       => 'Suitable',
        'advisory_message'     => 'Favorable conditions.',
        'is_outdated'          => false,
        'fetched_at'           => now()->subHours(5),
    ]);

    // Simulate API connection failure
    Http::fake([
        'https://api.open-meteo.com/v1/forecast*' => Http::response(null, 500),
    ]);

    $service = new WeatherService(new WeatherAdvisoryService());
    $result = $service->getForecastForTourDate($tourDate, forceFresh: true);

    expect($result)->not->toBeNull()
        ->and($result->id)->toBe($existing->id)
        ->and($result->is_outdated)->toBeTrue();
});

