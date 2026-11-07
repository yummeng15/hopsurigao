<?php

namespace App\Services\Reports;

use App\Models\WeatherForecast;
use Illuminate\Database\Eloquent\Builder;

class WeatherAdvisoryReportService extends BaseReportService
{
    public function getReportSlug(): string
    {
        return 'weather-advisory';
    }

    public function getReportTitle(): string
    {
        return 'Maritime Weather Advisory & Safety Report';
    }

    public function getViewName(): string
    {
        return 'reports.weather-advisory-report';
    }

    public function getData(array $filters): array
    {
        [$dateFrom, $dateTo, $periodLabel] = $this->parseDateRange($filters);

        $query = WeatherForecast::query()
            ->with([
                'tourDate.package:id,package_name,destination',
                'tourDate.bookings' => function ($q) {
                    $q->whereIn('booking_status', ['pending', 'confirmed']);
                },
            ]);

        // Date range filter
        if ($dateFrom && $dateTo) {
            $query->whereBetween('forecast_date', [$dateFrom, $dateTo]);
        }

        // Advisory level filter
        if (!empty($filters['advisory_level']) && $filters['advisory_level'] !== 'all') {
            $query->where('advisory_level', $filters['advisory_level']);
        }

        // Package filter
        if (!empty($filters['package_id']) && $filters['package_id'] !== 'all') {
            $query->whereHas('tourDate', function (Builder $q) use ($filters) {
                $q->where('package_id', $filters['package_id']);
            });
        }

        $forecasts = $query->orderBy('forecast_date', 'asc')->get();

        $greenCount = $forecasts->where('advisory_level', 'green')->count();
        $yellowCount = $forecasts->where('advisory_level', 'yellow')->count();
        $orangeCount = $forecasts->where('advisory_level', 'orange')->count();
        $redCount = $forecasts->where('advisory_level', 'red')->count();

        $affectedGuests = 0;
        foreach ($forecasts as $wf) {
            if (in_array($wf->advisory_level, ['yellow', 'orange', 'red'])) {
                $affectedGuests += (int) $wf->tourDate?->bookings->sum('number_of_guests');
            }
        }

        $summary = [
            'total_forecasted_dates' => $forecasts->count(),
            'safe_green_count' => $greenCount,
            'caution_yellow_count' => $yellowCount,
            'warning_orange_count' => $orangeCount,
            'severe_red_count' => $redCount,
            'affected_guests_in_advisory' => $affectedGuests,
        ];

        $records = $forecasts->map(function (WeatherForecast $wf) {
            $bookedGuests = (int) $wf->tourDate?->bookings->sum('number_of_guests');

            return [
                'id' => $wf->id,
                'package_name' => $wf->tourDate?->package?->package_name ?? 'N/A',
                'destination' => $wf->location ?: ($wf->tourDate?->package?->destination ?? 'Surigao'),
                'forecast_date' => $wf->forecast_date?->format('M d, Y') ?? 'N/A',
                'weather_condition' => $wf->weather_condition,
                'temperature_range' => ($wf->temperature_min !== null && $wf->temperature_max !== null)
                    ? round((float) $wf->temperature_min) . '°C - ' . round((float) $wf->temperature_max) . '°C'
                    : 'N/A',
                'rainfall_probability' => $wf->rainfall_probability !== null ? $wf->rainfall_probability . '%' : 'N/A',
                'wind_speed' => $wf->wind_speed !== null ? round((float) $wf->wind_speed, 1) . ' km/h' : 'N/A',
                'wind_gusts' => $wf->wind_gusts !== null ? round((float) $wf->wind_gusts, 1) . ' km/h' : 'N/A',
                'advisory_level' => $wf->advisory_level ?? 'green',
                'advisory_badge' => $wf->advisory_badge ?? 'Normal',
                'advisory_message' => $wf->advisory_message ?: 'Normal maritime conditions expected.',
                'booked_guests' => $bookedGuests,
                'fetched_at' => $wf->fetched_at?->format('M d, Y h:i A') ?? 'N/A',
            ];
        })->toArray();

        return [
            'period_label' => $periodLabel,
            'summary' => $summary,
            'records' => $records,
            'meta' => [
                'filters_applied' => array_filter($filters),
            ],
        ];
    }
}

