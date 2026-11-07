<?php

namespace App\Services;

class WeatherAdvisoryService
{
    /**
     * WMO Weather Interpretation Codes (WW)
     * Maps WMO numeric codes to human readable text and severity hints.
     */
    private const WMO_CODES = [
        0 => 'Clear Sky',
        1 => 'Mainly Clear',
        2 => 'Partly Cloudy',
        3 => 'Overcast',
        45 => 'Foggy',
        48 => 'Depositing Rime Fog',
        51 => 'Light Drizzle',
        53 => 'Moderate Drizzle',
        55 => 'Dense Drizzle',
        56 => 'Light Freezing Drizzle',
        57 => 'Dense Freezing Drizzle',
        61 => 'Slight Rain',
        63 => 'Moderate Rain',
        65 => 'Heavy Rain',
        66 => 'Light Freezing Rain',
        67 => 'Heavy Freezing Rain',
        71 => 'Slight Snow Fall',
        73 => 'Moderate Snow Fall',
        75 => 'Heavy Snow Fall',
        77 => 'Snow Grains',
        80 => 'Slight Rain Showers',
        81 => 'Moderate Rain Showers',
        82 => 'Violent Rain Showers',
        85 => 'Slight Snow Showers',
        86 => 'Heavy Snow Showers',
        95 => 'Thunderstorm',
        96 => 'Thunderstorm with Slight Hail',
        99 => 'Thunderstorm with Heavy Hail',
    ];

    /**
     * Get textual description for a WMO weather code.
     */
    public function getWeatherCondition(?int $code): string
    {
        if ($code === null) {
            return 'Fair Weather';
        }

        return self::WMO_CODES[$code] ?? 'Variable Weather';
    }

    /**
     * Evaluate weather metrics and compute maritime advisory level and message.
     *
     * @param array{
     *     weather_code?: int|null,
     *     wind_speed?: float|int|null,
     *     wind_gusts?: float|int|null,
     *     rainfall?: float|int|null,
     *     rainfall_probability?: int|null,
     *     temperature_max?: float|int|null,
     *     temperature_min?: float|int|null,
     * } $data
     * @return array{
     *     level: 'green'|'yellow'|'orange'|'red',
     *     badge: string,
     *     message: string,
     *     condition: string,
     *     score: int
     * }
     */
    public function evaluate(array $data): array
    {
        $code = $data['weather_code'] ?? null;
        $windSpeed = (float) ($data['wind_speed'] ?? 0);
        $windGusts = (float) ($data['wind_gusts'] ?? $windSpeed);
        $rainfall = (float) ($data['rainfall'] ?? 0);
        $rainProb = (int) ($data['rainfall_probability'] ?? 0);

        $condition = $this->getWeatherCondition($code);

        // 1. RED (Dangerous / Severe Storm or Gale)
        // High risk to sea vessels and passenger safety. Immediate warning.
        if (
            in_array($code, [96, 99], true) ||
            $windSpeed >= 50.0 ||
            $windGusts >= 60.0 ||
            $rainfall >= 30.0
        ) {
            return [
                'level' => 'red',
                'badge' => 'Dangerous',
                'condition' => $condition,
                'score' => 4,
                'message' => 'Severe maritime weather conditions detected (gale-force winds, intense rainfall, or severe storm). Marine tours are strongly discouraged or suspended per maritime safety protocols.',
            ];
        }

        // 2. ORANGE (Unfavorable / Choppy Seas & Heavy Rain)
        // Significant sea chop or heavy precipitation. Tours may be delayed or cancelled.
        if (
            $code === 95 ||
            $code === 65 ||
            $code === 82 ||
            $windSpeed >= 35.0 ||
            $windGusts >= 45.0 ||
            $rainfall >= 15.0 ||
            $rainProb >= 75
        ) {
            return [
                'level' => 'orange',
                'badge' => 'Unfavorable',
                'condition' => $condition,
                'score' => 3,
                'message' => 'Unfavorable weather and choppy sea conditions expected. Tour operations may experience delays, route adjustments, or potential suspension. Please stay tuned for operator notices.',
            ];
        }

        // 3. YELLOW (Caution / Moderate Winds & Scattered Showers)
        // Tours operational but travelers should prepare for light sea spray or showers.
        if (
            in_array($code, [51, 53, 55, 61, 63, 80, 81], true) ||
            $windSpeed >= 20.0 ||
            $windGusts >= 30.0 ||
            $rainfall >= 4.0 ||
            $rainProb >= 40
        ) {
            return [
                'level' => 'yellow',
                'badge' => 'Caution / Monitor',
                'condition' => $condition,
                'score' => 2,
                'message' => 'Moderate sea breeze and scattered precipitation expected. Tours are operational, but passengers should monitor weather updates and bring water-resistant protection.',
            ];
        }

        // 4. GREEN (Suitable / Calm Sea & Clear to Partly Cloudy Skies)
        return [
            'level' => 'green',
            'badge' => 'Suitable',
            'condition' => $condition,
            'score' => 1,
            'message' => 'Calm seas and favorable weather conditions expected. Ideal conditions for island hopping, snorkeling, and boat excursions.',
        ];
    }

    /**
     * Orange and red advisories mean strong wind or dangerous weather.
     * Yellow stays open, with a caution shown to the guest.
     */
    public function blocksTravel(?string $level): bool
    {
        return in_array($level, ['orange', 'red'], true);
    }

    public function bookingClosure(bool $travelClosed, ?string $level): ?string
    {
        if ($this->blocksTravel($level)) {
            return 'Travel Closed: Unfavorable weather conditions. Booking is temporarily unavailable for this date. Please choose another available date.';
        }

        if ($travelClosed) {
            return 'This tour date has been closed by the tour operator.';
        }

        return null;
    }
}
