<?php

namespace App\Services;

use App\Models\Package;
use App\Models\TourDate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Idempotently ensure daily tour schedules for eligible published packages.
 */
class RollingTourScheduleService
{
    public const WINDOW_DAYS = 365;

    /**
     * Soft advisory only: packages have no certified vessel-limit column.
     * Capacities above this are preserved but flagged for operator verification.
     */
    public const ADVISORY_CAPACITY_LIMIT = 60;

    /**
     * @var list<array<string, mixed>>
     */
    public array $report = [];

    /**
     * @return list<array<string, mixed>>
     */
    public function ensure(
        int $days = self::WINDOW_DAYS,
        bool $dryRun = true,
        ?int $packageId = null,
        ?int $operatorId = null,
    ): array {
        $this->report = [];

        $days = max(1, min(366, $days));
        $start = Carbon::today();
        $end = Carbon::today()->addDays($days - 1);

        $packages = Package::withoutGlobalScopes()
            ->with(['tourOperator'])
            ->where('status', 'active')
            ->whereNotNull('tour_operator_id')
            ->when($packageId !== null, fn ($query) => $query->whereKey($packageId))
            ->when($operatorId !== null, fn ($query) => $query->where('tour_operator_id', $operatorId))
            ->orderBy('id')
            ->get();

        foreach ($packages as $package) {
            $this->report[] = $this->ensurePackage($package, $start, $end, $dryRun);
        }

        return $this->report;
    }

    /**
     * @return array<string, mixed>
     */
    private function ensurePackage(
        Package $package,
        Carbon $start,
        Carbon $end,
        bool $dryRun,
    ): array {
        $operator = $package->tourOperator;
        $base = [
            'package_id' => $package->id,
            'package_name' => $package->package_name,
            'operator_id' => $package->tour_operator_id,
            'boat_name' => $package->boat_name,
            'capacity' => null,
            'existing_complete' => 0,
            'missing' => 0,
            'create_dates' => 0,
            'create_times' => 0,
            'create_pickups' => 0,
            'skipped_boat_conflict' => 0,
            'capacity_flag' => null,
            'status' => 'skipped',
            'notes' => null,
        ];

        if ($operator === null || ! $operator->acceptsBookings()) {
            return [
                ...$base,
                'status' => 'ineligible',
                'notes' => 'Operator cannot accept bookings.',
            ];
        }

        $template = $this->resolveTemplate($package);

        if ($template === null) {
            return [
                ...$base,
                'status' => 'blocked',
                'notes' => 'No complete template date (needs departure time + pickup).',
            ];
        }

        $capacity = (int) $template['capacity'];
        $base['capacity'] = $capacity;
        $base['capacity_flag'] = $capacity > self::ADVISORY_CAPACITY_LIMIT
            ? "Capacity {$capacity} exceeds advisory vessel limit ".self::ADVISORY_CAPACITY_LIMIT.' (preserved; verify with operator).'
            : null;

        $existing = TourDate::withoutGlobalScopes()
            ->where('package_id', $package->id)
            ->whereDate('tour_date', '>=', $start->toDateString())
            ->whereDate('tour_date', '<=', $end->toDateString())
            ->withCount(['times', 'pickupSchedules'])
            ->get(['id', 'tour_date', 'capacity']);

        $completeByDate = [];
        foreach ($existing as $date) {
            if ($date->times_count > 0 && $date->pickup_schedules_count > 0) {
                $completeByDate[$date->tour_date->toDateString()] = true;
            }
        }

        $base['existing_complete'] = count($completeByDate);

        $conflictDates = $this->boatConflictDates(
            $package,
            $start->toDateString(),
            $end->toDateString(),
        );

        $missingDates = [];
        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            $key = $day->toDateString();

            if (isset($completeByDate[$key])) {
                continue;
            }

            if (isset($conflictDates[$key])) {
                $base['skipped_boat_conflict']++;

                continue;
            }

            $missingDates[] = $key;
        }

        $base['missing'] = count($missingDates);
        $base['create_dates'] = count($missingDates);
        $base['create_times'] = count($missingDates) * max(1, count($template['times']));
        $base['create_pickups'] = count($missingDates) * max(1, count($template['pickups']));

        if ($missingDates === []) {
            return [
                ...$base,
                'status' => 'complete',
                'notes' => 'Rolling window already complete.',
            ];
        }

        if ($dryRun) {
            return [
                ...$base,
                'status' => 'dry_run',
                'notes' => 'Would create '.count($missingDates).' daily schedule(s).',
            ];
        }

        $created = $this->insertMissing($package, $missingDates, $template);

        return [
            ...$base,
            'create_dates' => $created,
            'create_times' => $created * max(1, count($template['times'])),
            'create_pickups' => $created * max(1, count($template['pickups'])),
            'status' => 'updated',
            'notes' => "Created {$created} daily schedule(s).",
        ];
    }

    /**
     * @return array{
     *     capacity: int,
     *     times: list<array{time: string, description: ?string}>,
     *     pickups: list<array{pickup_location_id: int, pickup_time: string}>
     * }|null
     */
    private function resolveTemplate(Package $package): ?array
    {
        $candidates = TourDate::withoutGlobalScopes()
            ->where('package_id', $package->id)
            ->with(['times', 'pickupSchedules'])
            ->orderByDesc('tour_date')
            ->limit(30)
            ->get();

        $template = $candidates->first(
            fn (TourDate $date): bool => $date->times->isNotEmpty() && $date->pickupSchedules->isNotEmpty()
        );

        if ($template === null) {
            return null;
        }

        return [
            'capacity' => max(1, (int) $template->capacity),
            'times' => $template->times->map(fn ($time): array => [
                'time' => (string) ($time->getRawOriginal('time') ?: $time->time),
                'description' => $time->description,
            ])->values()->all(),
            'pickups' => $template->pickupSchedules->map(fn ($pickup): array => [
                'pickup_location_id' => (int) $pickup->pickup_location_id,
                'pickup_time' => (string) ($pickup->getRawOriginal('pickup_time') ?: $pickup->pickup_time),
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, true>
     */
    private function boatConflictDates(Package $package, string $start, string $end): array
    {
        $boat = trim((string) $package->boat_name);

        if ($boat === '' || $package->tour_operator_id === null) {
            return [];
        }

        $rows = DB::table('tour_dates as td')
            ->join('packages as p', 'p.id', '=', 'td.package_id')
            ->where('p.status', 'active')
            ->where('p.id', '<>', $package->id)
            ->where('p.tour_operator_id', $package->tour_operator_id)
            ->whereRaw('LOWER(TRIM(p.boat_name)) = ?', [mb_strtolower($boat)])
            ->whereBetween('td.tour_date', [$start, $end])
            ->pluck('td.tour_date')
            ->all();

        $lookup = [];
        foreach ($rows as $date) {
            $lookup[Carbon::parse($date)->toDateString()] = true;
        }

        return $lookup;
    }

    /**
     * @param  list<string>  $missingDates
     * @param  array{
     *     capacity: int,
     *     times: list<array{time: string, description: ?string}>,
     *     pickups: list<array{pickup_location_id: int, pickup_time: string}>
     * }  $template
     */
    private function insertMissing(Package $package, array $missingDates, array $template): int
    {
        $created = 0;

        DB::transaction(function () use ($package, $missingDates, $template, &$created): void {
            foreach ($missingDates as $date) {
                $existing = TourDate::withoutGlobalScopes()
                    ->where('package_id', $package->id)
                    ->whereDate('tour_date', $date)
                    ->lockForUpdate()
                    ->first();

                if ($existing === null) {
                    $tourDateId = DB::table('tour_dates')->insertGetId([
                        'package_id' => $package->id,
                        'tour_date' => $date,
                        'capacity' => $template['capacity'],
                        'travel_closed' => false,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    $tourDateId = $existing->id;
                }

                $hasTimes = DB::table('times')->where('tour_date_id', $tourDateId)->exists();
                if (! $hasTimes) {
                    foreach ($template['times'] as $time) {
                        DB::table('times')->insert([
                            'tour_date_id' => $tourDateId,
                            'time' => $time['time'],
                            'description' => $time['description'],
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }

                $hasPickups = DB::table('pickup_schedules')->where('tour_date_id', $tourDateId)->exists();
                if (! $hasPickups) {
                    foreach ($template['pickups'] as $pickup) {
                        DB::table('pickup_schedules')->insert([
                            'tour_date_id' => $tourDateId,
                            'pickup_location_id' => $pickup['pickup_location_id'],
                            'pickup_time' => $pickup['pickup_time'],
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }

                $complete = DB::table('times')->where('tour_date_id', $tourDateId)->exists()
                    && DB::table('pickup_schedules')->where('tour_date_id', $tourDateId)->exists();

                if ($complete) {
                    $created++;
                }
            }
        }, 3);

        return $created;
    }

    /**
     * @param  list<array<string, mixed>>  $report
     * @return array{packages: int, create_dates: int, create_times: int, create_pickups: int, flagged: int}
     */
    public function totals(array $report): array
    {
        return [
            'packages' => count($report),
            'create_dates' => (int) collect($report)->sum('create_dates'),
            'create_times' => (int) collect($report)->sum('create_times'),
            'create_pickups' => (int) collect($report)->sum('create_pickups'),
            'flagged' => (int) collect($report)->filter(fn (array $row): bool => filled($row['capacity_flag'] ?? null))->count(),
        ];
    }
}
