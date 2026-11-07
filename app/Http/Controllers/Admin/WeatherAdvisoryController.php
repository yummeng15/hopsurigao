<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TourDate;
use App\Services\WeatherAdvisoryService;
use App\Services\WeatherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WeatherAdvisoryController extends Controller
{
    public function __construct(
        private WeatherService $weatherService,
        private WeatherAdvisoryService $weatherAdvisory,
    ) {}

    /**
     * Render the Operator Weather Advisory dashboard.
     */
    public function index(): Response
    {
        return Inertia::render('admin/weather-advisories/Index');
    }

    /**
     * Retrieve paginated weather advisories for SimpleTable.
     */
    public function getData(Request $request): JsonResponse
    {
        $today = now()->toDateString();
        $futureLimit = now()->addDays(30)->toDateString();

        $query = TourDate::query()
            ->whereBetween('tour_date', [$today, $futureLimit])
            ->whereHas('package', function ($q) {
                $q->where('status', 'active');
            })
            ->with(['package', 'weatherForecast']);

        // Search Filter
        if ($search = trim((string) $request->input('search'))) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('package', function ($pq) use ($search) {
                    $pq->where('package_name', 'like', "%{$search}%")
                        ->orWhere('destination', 'like', "%{$search}%");
                })
                    ->orWhereHas('weatherForecast', function ($wq) use ($search) {
                        $wq->where('weather_condition', 'like', "%{$search}%")
                            ->orWhere('advisory_badge', 'like', "%{$search}%")
                            ->orWhere('advisory_level', 'like', "%{$search}%");
                    });
            });
        }

        // Advisory Level Filter
        if ($level = $request->input('advisory_level')) {
            if ($level !== 'all') {
                $query->whereHas('weatherForecast', function ($q) use ($level) {
                    $q->where('advisory_level', $level);
                });
            }
        }

        // Sorting
        $sort = $request->input('sort', 'tour_date');
        $order = in_array(strtolower((string) $request->input('order', 'asc')), ['asc', 'desc'], true)
            ? strtolower((string) $request->input('order', 'asc'))
            : 'asc';

        if ($sort === 'tour_date') {
            $query->orderBy('tour_date', $order);
        } elseif ($sort === 'package_name') {
            $query->join('packages', 'packages.id', '=', 'tour_dates.package_id')
                ->orderBy('packages.package_name', $order)
                ->select('tour_dates.*');
        } elseif ($sort === 'destination') {
            $query->join('packages', 'packages.id', '=', 'tour_dates.package_id')
                ->orderBy('packages.destination', $order)
                ->select('tour_dates.*');
        } else {
            $query->orderBy('tour_date', 'asc');
        }

        $perPage = (int) $request->input('per_page', 10);
        $tourDates = $query->paginate($perPage);

        $items = collect($tourDates->items())->map(function (TourDate $tourDate) {
            $forecast = $tourDate->weatherForecast;

            // Auto-fetch if missing
            if (! $forecast) {
                $forecast = $this->weatherService->getForecastForTourDate($tourDate, false);
            }

            $closure = $this->weatherAdvisory->bookingClosure(
                (bool) $tourDate->travel_closed,
                $forecast?->advisory_level,
            );

            return [
                'id' => $tourDate->id,
                'travel_closed' => (bool) $tourDate->travel_closed,
                'booking_closed' => $closure !== null,
                'booking_closed_reason' => $closure,
                'tour_date_id' => $tourDate->id,
                'tour_date' => $tourDate->tour_date->toDateString(),
                'package_id' => $tourDate->package_id,
                'package_name' => $tourDate->package?->package_name ?? '—',
                'destination' => $tourDate->package?->destination ?? '—',
                'capacity' => $tourDate->capacity,
                'weather_condition' => $forecast?->weather_condition ?? 'Forecast Pending',
                'weather_code' => $forecast?->weather_code,
                'temperature_min' => $forecast?->temperature_min !== null ? (float) $forecast->temperature_min : null,
                'temperature_max' => $forecast?->temperature_max !== null ? (float) $forecast->temperature_max : null,
                'temperature_current' => $forecast?->temperature_current !== null ? (float) $forecast->temperature_current : null,
                'rainfall_probability' => $forecast?->rainfall_probability ?? 0,
                'rainfall' => $forecast?->rainfall !== null ? (float) $forecast->rainfall : 0.0,
                'wind_speed' => $forecast?->wind_speed !== null ? (float) $forecast->wind_speed : 0.0,
                'wind_gusts' => $forecast?->wind_gusts !== null ? (float) $forecast->wind_gusts : 0.0,
                'wind_direction' => $forecast?->wind_direction,
                'humidity' => $forecast?->humidity,
                'advisory_level' => $forecast?->advisory_level ?? 'green',
                'advisory_badge' => $forecast?->advisory_badge ?? 'Suitable',
                'advisory_message' => $forecast?->advisory_message ?? 'Weather conditions are currently normal.',
                'is_outdated' => (bool) ($forecast?->is_outdated ?? false),
                'fetched_at' => $forecast?->fetched_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $tourDates->currentPage(),
                'last_page' => $tourDates->lastPage(),
                'per_page' => $tourDates->perPage(),
                'total' => $tourDates->total(),
            ],
        ]);
    }

    /**
     * Controlled manual refresh for a specific tour date.
     */
    public function refresh(TourDate $tourDate): RedirectResponse
    {
        $forecast = $this->weatherService->getForecastForTourDate($tourDate, forceFresh: true);

        if ($forecast) {
            Inertia::flash('toast', [
                'type' => 'success',
                'message' => "Weather advisory for {$tourDate->tour_date->format('M d, Y')} refreshed successfully.",
            ]);
        } else {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'Unable to refresh weather forecast at this time. Retaining cached advisory.',
            ]);
        }

        return back();
    }

    /**
     * Staff can close a date before the forecast turns dangerous, and reopen it later.
     * Orange and red weather still block booking even when this flag is open.
     */
    public function toggleTravel(TourDate $tourDate): RedirectResponse
    {
        $tourDate->update([
            'travel_closed' => ! $tourDate->travel_closed,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $tourDate->travel_closed
                ? 'Travel is closed for this date. Guests cannot book it.'
                : 'Manual travel closure lifted.',
        ]);

        return back();
    }

    /**
     * Batch refresh all upcoming tour date advisories.
     */
    public function refreshAll(): RedirectResponse
    {
        $result = $this->weatherService->updateUpcomingTourDates(14);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Refreshed weather data: {$result['updated']} of {$result['total']} tour date(s) updated.",
        ]);

        return back();
    }
}
