<?php

namespace App\Services;

use App\Models\Package;
use App\Models\TourDate;
use App\Models\User;
use App\Models\WeatherForecast;
use App\Notifications\WeatherAdvisoryNotification;
use Exception;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class WeatherService
{
    /**
     * Common Philippine destination coordinates for rapid fallback.
     */
    private const KNOWN_DESTINATIONS = [
        'camotes' => ['lat' => 10.6698, 'lon' => 124.3822],
        'camotes islands' => ['lat' => 10.6698, 'lon' => 124.3822],
        'cebu' => ['lat' => 10.3157, 'lon' => 123.8854],
        'cebu highlands' => ['lat' => 10.3700, 'lon' => 123.8400],
        'oslob' => ['lat' => 9.5167,  'lon' => 123.4333],
        'osmena peak' => ['lat' => 9.8197,  'lon' => 123.4475],
        'moalboal' => ['lat' => 9.9564,  'lon' => 123.4007],
        'siargao' => ['lat' => 9.8583,  'lon' => 126.0461],
        'naked island' => ['lat' => 9.7333,  'lon' => 126.1500],
        'coron' => ['lat' => 11.9986, 'lon' => 120.2043],
        'el nido' => ['lat' => 11.1956, 'lon' => 119.4175],
        'boracay' => ['lat' => 11.9674, 'lon' => 121.9248],
        'mactan' => ['lat' => 10.2975, 'lon' => 123.9686],
        'panglao' => ['lat' => 9.5786,  'lon' => 123.7725],
        'bohol' => ['lat' => 9.8500,  'lon' => 124.1435],
    ];

    public function __construct(
        private WeatherAdvisoryService $advisoryService
    ) {}

    /**
     * Create an HTTP client configured with timeouts and SSL options.
     */
    private function client(int $timeout = 6): PendingRequest
    {
        $client = Http::timeout($timeout)->retry(1, 250);

        if (! config('services.weather.verify_ssl', false)) {
            $client = $client->withoutVerifying();
        }

        return $client;
    }

    /**
     * Resolve latitude and longitude for a Package.
     * Checks package columns first, then cached geocode, then API geocoding with local fallback.
     *
     * @return array{lat: float, lon: float}
     */
    public function getCoordinatesForPackage(Package $package): array
    {
        // 1. Direct coordinates stored on package
        if ($package->latitude !== null && $package->longitude !== null) {
            return [
                'lat' => (float) $package->latitude,
                'lon' => (float) $package->longitude,
            ];
        }

        $destination = trim($package->destination);
        $lookupKey = strtolower($destination);

        // 2. Check local quick lookup table
        if (isset(self::KNOWN_DESTINATIONS[$lookupKey])) {
            $coords = self::KNOWN_DESTINATIONS[$lookupKey];
            $package->update([
                'latitude' => $coords['lat'],
                'longitude' => $coords['lon'],
            ]);

            return $coords;
        }

        // 3. Check cache
        $cacheKey = 'geocoding:'.md5($lookupKey);
        $cached = Cache::get($cacheKey);
        if ($cached && is_array($cached) && isset($cached['lat'], $cached['lon'])) {
            return $cached;
        }

        // 4. Query Open-Meteo Geocoding API
        try {
            $geoEndpoint = config(
                'services.weather.geocoding_endpoint',
                'https://geocoding-api.open-meteo.com/v1/search'
            );

            $response = $this->client(5)->get($geoEndpoint, [
                'name' => $destination,
                'count' => 1,
                'language' => 'en',
                'format' => 'json',
            ]);

            if ($response->successful()) {
                $results = $response->json('results');
                if (! empty($results) && isset($results[0]['latitude'], $results[0]['longitude'])) {
                    $coords = [
                        'lat' => (float) $results[0]['latitude'],
                        'lon' => (float) $results[0]['longitude'],
                    ];

                    Cache::put($cacheKey, $coords, now()->addDays(30));

                    $package->update([
                        'latitude' => $coords['lat'],
                        'longitude' => $coords['lon'],
                    ]);

                    return $coords;
                }
            }
        } catch (Exception $e) {
            Log::warning("Geocoding failed for package destination '{$destination}': ".$e->getMessage());
        }

        // 5. Default central Visayas / Cebu fallback coordinates
        $defaultCoords = ['lat' => 10.3157, 'lon' => 123.8854];
        Cache::put($cacheKey, $defaultCoords, now()->addDays(7));

        return $defaultCoords;
    }

    /**
     * Retrieve the weather forecast and advisory for a specific TourDate.
     * Reads from cache / database if fresh, otherwise calls the external API.
     */
    public function getForecastForTourDate(TourDate $tourDate, bool $forceFresh = false): ?WeatherForecast
    {
        $cacheKey = "weather:forecast:tour_date:{$tourDate->id}";
        $cacheTtl = (int) config('services.weather.cache_ttl', 7200);

        if (! $forceFresh) {
            $cached = Cache::get($cacheKey);
            if ($cached instanceof WeatherForecast) {
                return $cached;
            }
        }

        $tourDate->loadMissing('package');
        $package = $tourDate->package;

        if (! $package) {
            return null;
        }

        $coords = $this->getCoordinatesForPackage($package);
        $targetDateStr = $tourDate->tour_date->toDateString();

        try {
            $endpoint = config(
                'services.weather.endpoint',
                'https://api.open-meteo.com/v1/forecast'
            );
            $timezone = config('services.weather.timezone', 'Asia/Manila');
            $apiKey = config('services.weather.api_key');

            $params = [
                'latitude' => $coords['lat'],
                'longitude' => $coords['lon'],
                'daily' => implode(',', [
                    'weather_code',
                    'temperature_2m_max',
                    'temperature_2m_min',
                    'precipitation_sum',
                    'precipitation_probability_max',
                    'wind_speed_10m_max',
                    'wind_gusts_10m_max',
                    'wind_direction_10m_dominant',
                ]),
                'current' => 'temperature_2m,relative_humidity_2m,weather_code,wind_speed_10m',
                'timezone' => $timezone,
                'forecast_days' => 16,
            ];

            if (! empty($apiKey)) {
                $params['apikey'] = $apiKey;
            }

            $response = $this->client(6)->get($endpoint, $params);

            if ($response->successful()) {
                $data = $response->json();
                $daily = $data['daily'] ?? [];
                $current = $data['current'] ?? [];

                $dateIndex = false;
                if (isset($daily['time']) && is_array($daily['time'])) {
                    $dateIndex = array_search($targetDateStr, $daily['time'], true);
                }

                // If date is within forecast range, use daily forecast; else use current or index 0
                $weatherCode = null;
                $tempMin = null;
                $tempMax = null;
                $rainfall = null;
                $rainProb = null;
                $windSpeed = null;
                $windGusts = null;
                $windDirection = null;
                $humidity = $current['relative_humidity_2m'] ?? null;
                $tempCurrent = $current['temperature_2m'] ?? null;

                if ($dateIndex !== false) {
                    $weatherCode = $daily['weather_code'][$dateIndex] ?? null;
                    $tempMax = $daily['temperature_2m_max'][$dateIndex] ?? null;
                    $tempMin = $daily['temperature_2m_min'][$dateIndex] ?? null;
                    $rainfall = $daily['precipitation_sum'][$dateIndex] ?? null;
                    $rainProb = $daily['precipitation_probability_max'][$dateIndex] ?? null;
                    $windSpeed = $daily['wind_speed_10m_max'][$dateIndex] ?? null;
                    $windGusts = $daily['wind_gusts_10m_max'][$dateIndex] ?? null;
                    $windDirection = $daily['wind_direction_10m_dominant'][$dateIndex] ?? null;
                } else {
                    // Fallback to today / current metrics
                    $weatherCode = $current['weather_code'] ?? ($daily['weather_code'][0] ?? null);
                    $tempMax = $daily['temperature_2m_max'][0] ?? $tempCurrent;
                    $tempMin = $daily['temperature_2m_min'][0] ?? $tempCurrent;
                    $rainfall = $daily['precipitation_sum'][0] ?? 0;
                    $rainProb = $daily['precipitation_probability_max'][0] ?? 0;
                    $windSpeed = $current['wind_speed_10m'] ?? ($daily['wind_speed_10m_max'][0] ?? 0);
                    $windGusts = $daily['wind_gusts_10m_max'][0] ?? ($windSpeed * 1.3);
                }

                // Compute advisory
                $advisory = $this->advisoryService->evaluate([
                    'weather_code' => $weatherCode,
                    'wind_speed' => $windSpeed,
                    'wind_gusts' => $windGusts,
                    'rainfall' => $rainfall,
                    'rainfall_probability' => $rainProb,
                    'temperature_max' => $tempMax,
                    'temperature_min' => $tempMin,
                ]);

                // Check previous advisory level for change detection
                $previousForecast = WeatherForecast::where('tour_date_id', $tourDate->id)->first();
                $previousLevel = $previousForecast?->advisory_level;

                $forecast = WeatherForecast::updateOrCreate(
                    [
                        'tour_date_id' => $tourDate->id,
                    ],
                    [
                        'forecast_date' => $targetDateStr,
                        'location' => $package->destination,
                        'latitude' => $coords['lat'],
                        'longitude' => $coords['lon'],
                        'temperature_min' => $tempMin,
                        'temperature_max' => $tempMax,
                        'temperature_current' => $tempCurrent,
                        'weather_condition' => $advisory['condition'],
                        'weather_code' => $weatherCode,
                        'rainfall_probability' => $rainProb,
                        'rainfall' => $rainfall,
                        'wind_speed' => $windSpeed,
                        'wind_gusts' => $windGusts,
                        'wind_direction' => $windDirection,
                        'humidity' => $humidity,
                        'advisory_level' => $advisory['level'],
                        'advisory_badge' => $advisory['badge'],
                        'advisory_message' => $advisory['message'],
                        'is_outdated' => false,
                        'fetched_at' => now(),
                    ]
                );

                // Anti-spam rule: Only notify if the advisory level actually shifted
                $hasChanged = ($previousLevel !== null && $previousLevel !== $advisory['level'])
                    || ($previousLevel === null && $advisory['level'] !== 'green');

                if ($hasChanged) {
                    $this->notifyAdvisoryChange(
                        $tourDate,
                        $advisory['level'],
                        $advisory['badge'],
                        $advisory['message'],
                        $previousLevel
                    );
                }

                Cache::put($cacheKey, $forecast, $cacheTtl);

                return $forecast;
            }
        } catch (Exception $e) {
            Log::warning("Weather API request failed for TourDate {$tourDate->id} ({$targetDateStr}): ".$e->getMessage());
        }

        // Fallback to previously stored database record if API is unreachable
        $existing = WeatherForecast::where('tour_date_id', $tourDate->id)->latest('fetched_at')->first();
        if ($existing) {
            $existing->is_outdated = true;
            Cache::put($cacheKey, $existing, 1800); // 30 min cache for fallback

            return $existing;
        }

        return null;
    }

    /**
     * Batch refresh all upcoming tour dates within a specified future window.
     *
     * @return array{total: int, updated: int, failed: int}
     */
    public function updateUpcomingTourDates(int $daysAhead = 14): array
    {
        $today = now()->toDateString();
        $futureLimit = now()->addDays($daysAhead)->toDateString();

        $tourDates = TourDate::query()
            ->whereBetween('tour_date', [$today, $futureLimit])
            ->whereHas('package', function ($q) {
                $q->where('status', 'active');
            })
            ->with('package')
            ->orderBy('tour_date')
            ->get();

        $total = $tourDates->count();
        $updated = 0;
        $failed = 0;

        foreach ($tourDates as $tourDate) {
            $forecast = $this->getForecastForTourDate($tourDate, forceFresh: true);
            if ($forecast) {
                $updated++;
            } else {
                $failed++;
            }
        }

        return [
            'total' => $total,
            'updated' => $updated,
            'failed' => $failed,
        ];
    }

    /**
     * Save a forecast for upcoming dates that do not have one yet.
     * One Open-Meteo request covers the 16 days that service can predict.
     */
    public function fillMissingForecasts(Package $package): int
    {
        $tourDates = TourDate::query()
            ->where('package_id', $package->id)
            ->whereDate('tour_date', '>=', now()->toDateString())
            ->whereDate('tour_date', '<=', now()->addDays(15)->toDateString())
            ->whereDoesntHave('weatherForecast')
            ->orderBy('tour_date')
            ->get();

        if ($tourDates->isEmpty()) {
            return 0;
        }

        $coords = $this->getCoordinatesForPackage($package);
        $endpoint = config('services.weather.endpoint', 'https://api.open-meteo.com/v1/forecast');
        $timezone = config('services.weather.timezone', 'Asia/Manila');

        try {
            $response = $this->client(12)->get($endpoint, [
                'latitude' => $coords['lat'],
                'longitude' => $coords['lon'],
                'daily' => implode(',', [
                    'weather_code',
                    'temperature_2m_max',
                    'temperature_2m_min',
                    'precipitation_sum',
                    'precipitation_probability_max',
                    'wind_speed_10m_max',
                    'wind_gusts_10m_max',
                    'wind_direction_10m_dominant',
                ]),
                'current' => 'temperature_2m,relative_humidity_2m,weather_code,wind_speed_10m',
                'timezone' => $timezone,
                'forecast_days' => 16,
            ]);
        } catch (Exception $e) {
            Log::warning('Weather forecast fill failed for package '.$package->id.': '.$e->getMessage());

            return 0;
        }

        if (! $response->successful()) {
            return 0;
        }

        $daily = $response->json('daily') ?? [];
        $current = $response->json('current') ?? [];
        $times = is_array($daily['time'] ?? null) ? $daily['time'] : [];
        $saved = 0;

        foreach ($tourDates as $tourDate) {
            $dateIndex = array_search($tourDate->tour_date->toDateString(), $times, true);

            if ($dateIndex === false) {
                continue;
            }

            $metrics = [
                'weather_code' => $daily['weather_code'][$dateIndex] ?? null,
                'temperature_max' => $daily['temperature_2m_max'][$dateIndex] ?? null,
                'temperature_min' => $daily['temperature_2m_min'][$dateIndex] ?? null,
                'rainfall' => $daily['precipitation_sum'][$dateIndex] ?? null,
                'rainfall_probability' => $daily['precipitation_probability_max'][$dateIndex] ?? null,
                'wind_speed' => $daily['wind_speed_10m_max'][$dateIndex] ?? null,
                'wind_gusts' => $daily['wind_gusts_10m_max'][$dateIndex] ?? null,
            ];
            $advisory = $this->advisoryService->evaluate($metrics);

            WeatherForecast::query()->updateOrCreate(
                ['tour_date_id' => $tourDate->id],
                [
                    'forecast_date' => $tourDate->tour_date->toDateString(),
                    'location' => $package->destination,
                    'latitude' => $coords['lat'],
                    'longitude' => $coords['lon'],
                    'temperature_min' => $metrics['temperature_min'],
                    'temperature_max' => $metrics['temperature_max'],
                    'temperature_current' => $current['temperature_2m'] ?? null,
                    'weather_condition' => $advisory['condition'],
                    'weather_code' => $metrics['weather_code'],
                    'rainfall_probability' => $metrics['rainfall_probability'],
                    'rainfall' => $metrics['rainfall'],
                    'wind_speed' => $metrics['wind_speed'],
                    'wind_gusts' => $metrics['wind_gusts'],
                    'wind_direction' => $daily['wind_direction_10m_dominant'][$dateIndex] ?? null,
                    'humidity' => $current['relative_humidity_2m'] ?? null,
                    'advisory_level' => $advisory['level'],
                    'advisory_badge' => $advisory['badge'],
                    'advisory_message' => $advisory['message'],
                    'is_outdated' => false,
                    'fetched_at' => now(),
                ],
            );

            $saved++;
        }

        return $saved;
    }

    /**
     * Dispatch WeatherAdvisoryNotification to affected tourists and operators.
     */
    private function notifyAdvisoryChange(
        TourDate $tourDate,
        string $advisoryLevel,
        string $advisoryBadge,
        string $advisoryMessage,
        ?string $previousLevel = null
    ): void {
        try {
            $notification = new WeatherAdvisoryNotification(
                $tourDate,
                $advisoryLevel,
                $advisoryBadge,
                $advisoryMessage,
                $previousLevel
            );

            // 1. Notify tourists with active bookings on this specific TourDate
            $tourists = $tourDate->bookings()
                ->whereIn('booking_status', ['pending', 'confirmed'])
                ->with('user')
                ->get()
                ->pluck('user')
                ->filter()
                ->unique('id');

            if ($tourists->isNotEmpty()) {
                Notification::send($tourists, $notification);
            }

            // 2. Notify all active Operators & Superadmins
            $operators = User::whereHas('role', fn ($q) => $q->whereIn('name', ['Admin', 'Superadmin']))->get();
            if ($operators->isNotEmpty()) {
                Notification::send($operators, $notification);
            }
        } catch (Exception $e) {
            Log::warning("Failed to dispatch weather advisory notification for TourDate {$tourDate->id}: ".$e->getMessage());
        }
    }
}
