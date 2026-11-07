<?php

namespace App\Services;

use App\Models\Package;
use App\Models\TourDate;
use Illuminate\Support\Carbon;

class TourDateAvailabilityService
{
    public const WINDOW_DAYS = 60;

    public function __construct(
        private WeatherService $weather,
    ) {}

    /**
     * For operator-owned packages, tour dates are created explicitly in
     * /operator/packages/{id}/schedules. Never invent dates on a tourist visit.
     *
     * Preserve the legacy 60-day template behavior ONLY for older unassigned
     * packages while those are being migrated to an operator.
     */
    public function ensure(Package $package): void
    {
        if ($package->tour_operator_id !== null) {
            $this->weather->fillMissingForecasts($package);

            return;
        }

        $template = TourDate::query()
            ->where('package_id', $package->id)
            ->with(['times', 'pickupSchedules'])
            ->orderByDesc('tour_date')
            ->first();

        if ($template === null) {
            return;
        }

        $start = Carbon::today();
        $end = Carbon::today()->addDays(self::WINDOW_DAYS - 1);

        $existing = TourDate::query()
            ->where('package_id', $package->id)
            ->whereDate('tour_date', '>=', $start->toDateString())
            ->whereDate('tour_date', '<=', $end->toDateString())
            ->pluck('tour_date')
            ->map(fn ($date): string => Carbon::parse($date)->toDateString())
            ->all();

        $existing = array_flip($existing);

        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            $key = $day->toDateString();

            if (isset($existing[$key])) {
                continue;
            }

            $tourDate = TourDate::query()->create([
                'package_id' => $package->id,
                'tour_date' => $key,
                'capacity' => $template->capacity > 0 ? $template->capacity : 25,
                'travel_closed' => false,
            ]);

            foreach ($template->times as $time) {
                $tourDate->times()->create([
                    'time' => $time->getRawOriginal('time') ?: $time->time,
                    'description' => $time->description,
                ]);
            }

            foreach ($template->pickupSchedules as $pickup) {
                $tourDate->pickupSchedules()->create([
                    'pickup_location_id' => $pickup->pickup_location_id,
                    'pickup_time' => $pickup->getRawOriginal('pickup_time') ?: $pickup->pickup_time,
                ]);
            }
        }

        $this->weather->fillMissingForecasts($package);
    }
}
