<?php

use App\Models\Package;
use App\Models\TourDate;
use App\Services\TourDateAvailabilityService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

uses(DatabaseTransactions::class);

test('every day in the booking window is offered and a closed date stays closed', function () {
    $package = Package::query()->where('status', 'active')->where('under_maintenance', false)->first();

    expect($package)->not->toBeNull();

    Http::fake([
        'api.open-meteo.com/*' => Http::response([
            'daily' => ['time' => []],
            'current' => [],
        ]),
    ]);

    $template = TourDate::query()->where('package_id', $package->id)->first();

    expect($template)->not->toBeNull();

    $closedDay = Carbon::today()->addDay()->toDateString();

    $closed = TourDate::query()->updateOrCreate(
        [
            'package_id' => $package->id,
            'tour_date' => $closedDay,
        ],
        [
            'capacity' => $template->capacity,
            'travel_closed' => true,
        ],
    );

    app(TourDateAvailabilityService::class)->ensure($package);

    $offered = TourDate::query()
        ->where('package_id', $package->id)
        ->whereDate('tour_date', '>=', Carbon::today()->toDateString())
        ->whereDate('tour_date', '<=', Carbon::today()->addDays(TourDateAvailabilityService::WINDOW_DAYS - 1)->toDateString())
        ->count();

    $beyond = Carbon::today()->addDays(TourDateAvailabilityService::WINDOW_DAYS + 5)->toDateString();

    expect($offered)->toBe(TourDateAvailabilityService::WINDOW_DAYS)
        ->and($closed->fresh()->travel_closed)->toBeTrue()
        ->and(TourDate::query()->where('package_id', $package->id)->whereDate('tour_date', $beyond)->exists())->toBeFalse();
});
