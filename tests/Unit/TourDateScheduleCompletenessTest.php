<?php

use App\Services\TourDateBookingAvailability;
use App\Services\WeatherAdvisoryService;
use Tests\TestCase;

uses(TestCase::class);

it('exposes a dedicated no_schedule status constant for calendar markers', function (): void {
    expect(TourDateBookingAvailability::STATUS_NO_SCHEDULE)->toBe('no_schedule');

    $service = new TourDateBookingAvailability(new WeatherAdvisoryService);

    expect($service)->toBeInstanceOf(TourDateBookingAvailability::class);
});
