<?php

use App\Services\WeatherAdvisoryService;

test('it accurately maps WMO weather codes to condition strings', function () {
    $service = new WeatherAdvisoryService;

    expect($service->getWeatherCondition(0))->toBe('Clear Sky')
        ->and($service->getWeatherCondition(2))->toBe('Partly Cloudy')
        ->and($service->getWeatherCondition(61))->toBe('Slight Rain')
        ->and($service->getWeatherCondition(95))->toBe('Thunderstorm')
        ->and($service->getWeatherCondition(null))->toBe('Fair Weather');
});

test('it evaluates calm conditions as green advisory', function () {
    $service = new WeatherAdvisoryService;

    $result = $service->evaluate([
        'weather_code' => 0,
        'wind_speed' => 12.0,
        'wind_gusts' => 18.0,
        'rainfall' => 0.0,
        'rainfall_probability' => 10,
    ]);

    expect($result['level'])->toBe('green')
        ->and($result['badge'])->toBe('Suitable')
        ->and($result['condition'])->toBe('Clear Sky');
});

test('it evaluates moderate conditions as yellow caution advisory', function () {
    $service = new WeatherAdvisoryService;

    $result = $service->evaluate([
        'weather_code' => 61,
        'wind_speed' => 25.0,
        'wind_gusts' => 32.0,
        'rainfall' => 6.0,
        'rainfall_probability' => 50,
    ]);

    expect($result['level'])->toBe('yellow')
        ->and($result['badge'])->toBe('Caution / Monitor');
});

test('it evaluates choppy and unfavorable conditions as orange advisory', function () {
    $service = new WeatherAdvisoryService;

    $result = $service->evaluate([
        'weather_code' => 95,
        'wind_speed' => 38.0,
        'wind_gusts' => 48.0,
        'rainfall' => 18.0,
        'rainfall_probability' => 80,
    ]);

    expect($result['level'])->toBe('orange')
        ->and($result['badge'])->toBe('Unfavorable');
});

test('it evaluates severe storms or gale winds as red dangerous advisory', function () {
    $service = new WeatherAdvisoryService;

    $result = $service->evaluate([
        'weather_code' => 99,
        'wind_speed' => 55.0,
        'wind_gusts' => 65.0,
        'rainfall' => 35.0,
        'rainfall_probability' => 95,
    ]);

    expect($result['level'])->toBe('red')
        ->and($result['badge'])->toBe('Dangerous');
});

test('strong wind and dangerous weather close booking while caution stays open', function () {
    $service = new WeatherAdvisoryService;

    expect($service->blocksTravel('red'))->toBeTrue()
        ->and($service->blocksTravel('orange'))->toBeTrue()
        ->and($service->blocksTravel('yellow'))->toBeFalse()
        ->and($service->blocksTravel('green'))->toBeFalse()
        ->and($service->bookingClosure(true, 'green'))->not->toBeNull()
        ->and($service->bookingClosure(false, 'yellow'))->toBeNull()
        ->and($service->bookingClosure(false, 'orange'))->not->toBeNull()
        ->and($service->bookingClosure(false, 'red'))->not->toBeNull();
});
