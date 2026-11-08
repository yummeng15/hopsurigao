<?php

use App\Models\Package;
use App\Models\TourDate;
use App\Models\TourOperator;
use App\Services\TourDateBookingAvailability;
use App\Services\WeatherAdvisoryService;
use Tests\TestCase;

uses(TestCase::class);

function availabilityService(): TourDateBookingAvailability
{
    return new TourDateBookingAvailability(new WeatherAdvisoryService);
}

function availabilityPackage(bool $maintenance = false, bool $operatorAccepts = true): Package
{
    $operator = Mockery::mock(TourOperator::class)->makePartial();
    $operator->id = 99;
    $operator->shouldReceive('acceptsBookings')->andReturn($operatorAccepts);

    $package = new Package([
        'package_name' => 'Availability Demo',
        'boat_name' => 'Demo Boat',
        'destination' => 'Basul Island',
        'price' => 1200,
        'status' => 'active',
        'under_maintenance' => $maintenance,
        'tour_operator_id' => 99,
    ]);
    $package->id = 55;
    $package->setRelation('tourOperator', $operator);

    return $package;
}

function availabilityTourDate(bool $travelClosed = false, int $capacity = 60): TourDate
{
    $tourDate = new TourDate([
        'package_id' => 55,
        'tour_date' => now()->addDays(3)->toDateString(),
        'capacity' => $capacity,
        'travel_closed' => $travelClosed,
    ]);
    $tourDate->id = 77;

    return $tourDate;
}

it('explains unfavorable weather as travel closed', function (): void {
    $result = availabilityService()->assess(
        availabilityPackage(),
        availabilityTourDate(),
        bookedGuests: 0,
        advisoryLevel: 'orange',
        hasForecastRow: true,
        hasCompleteSchedule: true,
    );

    expect($result['status'])->toBe(TourDateBookingAvailability::STATUS_TRAVEL_CLOSED_WEATHER)
        ->and($result['label'])->toBe('Travel Closed')
        ->and($result['bookable'])->toBeFalse()
        ->and($result['reason'])->toContain('Unfavorable weather conditions');
});

it('explains operator manual closure separately from weather', function (): void {
    $result = availabilityService()->assess(
        availabilityPackage(),
        availabilityTourDate(travelClosed: true),
        bookedGuests: 0,
        advisoryLevel: 'green',
        hasForecastRow: true,
        hasCompleteSchedule: true,
    );

    expect($result['status'])->toBe(TourDateBookingAvailability::STATUS_TRAVEL_CLOSED_MANUAL)
        ->and($result['reason'])->toBe('This tour date has been closed by the tour operator.')
        ->and($result['bookable'])->toBeFalse();
});

it('explains fully booked and maintenance reasons', function (): void {
    $full = availabilityService()->assess(
        availabilityPackage(),
        availabilityTourDate(capacity: 10),
        bookedGuests: 10,
        advisoryLevel: 'green',
        hasForecastRow: true,
        hasCompleteSchedule: true,
    );

    expect($full['status'])->toBe(TourDateBookingAvailability::STATUS_FULLY_BOOKED)
        ->and($full['reason'])->toBe('All guest slots have already been reserved.');

    $maintenance = availabilityService()->assess(
        availabilityPackage(maintenance: true),
        availabilityTourDate(),
        bookedGuests: 0,
        advisoryLevel: 'green',
        hasForecastRow: true,
        hasCompleteSchedule: true,
    );

    expect($maintenance['status'])->toBe(TourDateBookingAvailability::STATUS_UNDER_MAINTENANCE)
        ->and($maintenance['reason'])->toBe('The assigned boat is currently under maintenance.');
});

it('keeps dates bookable when forecast data is unavailable and shows a notice', function (): void {
    $result = availabilityService()->assess(
        availabilityPackage(),
        availabilityTourDate(),
        bookedGuests: 2,
        advisoryLevel: null,
        hasForecastRow: false,
        hasCompleteSchedule: true,
    );

    expect($result['bookable'])->toBeTrue()
        ->and($result['status'])->toBe(TourDateBookingAvailability::STATUS_AVAILABLE)
        ->and($result['forecast_notice'])->toContain('not yet available')
        ->and($result['remaining_capacity'])->toBe(58);
});

it('never marks incomplete schedules as seats open', function (): void {
    $result = availabilityService()->assess(
        availabilityPackage(),
        availabilityTourDate(),
        bookedGuests: 0,
        advisoryLevel: 'green',
        hasForecastRow: true,
        hasCompleteSchedule: false,
    );

    expect($result['bookable'])->toBeFalse()
        ->and($result['status'])->toBe(TourDateBookingAvailability::STATUS_NO_SCHEDULE)
        ->and($result['label'])->toBe('No Schedule Available')
        ->and($result['reason'])->toContain('no departure scheduled');
});
