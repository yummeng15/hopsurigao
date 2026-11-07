<?php

namespace Database\Seeders;

use App\Models\Package;
use App\Models\PickupLocation;
use App\Models\TourDate;
use App\Models\WeatherForecast;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class SurigaoDelNorteTourDateSeeder extends Seeder
{
    /**
     * @var array<string, list<string>>
     */
    private array $pickupNames = [
        'Surigao City Island Hopping' => [
            'Surigao City Boulevard Wharf',
            'Mabua Pebble Beach Station',
        ],
        'Silop Cave Explorer' => [
            'Surigao City Boulevard Wharf',
        ],
    ];

    /**
     * @var array<string, list<array{time: string, description: string}>>
     */
    private array $itineraries = [
        'Surigao City Island Hopping' => [
            ['time' => '06:30', 'description' => 'Assembly and safety briefing at Surigao City Boulevard Wharf'],
            ['time' => '07:00', 'description' => 'Boat departure across Surigao Strait'],
            ['time' => '07:40', 'description' => 'Basul Island swimming and beach time'],
            ['time' => '09:30', 'description' => 'Pagkawasan Beach on Hikdop Island'],
            ['time' => '11:00', 'description' => 'Buenavista Cave exploration'],
            ['time' => '12:30', 'description' => 'Picnic lunch on Hikdop Island'],
            ['time' => '14:30', 'description' => 'Return cruise to Surigao City'],
            ['time' => '15:30', 'description' => 'Arrival and tour ends'],
        ],
        'Silop Cave Explorer' => [
            ['time' => '07:30', 'description' => 'Meet-up at Surigao City Boulevard Wharf'],
            ['time' => '08:00', 'description' => 'Transfer to Brgy. Silop'],
            ['time' => '08:30', 'description' => 'Cave briefing, helmet, and lights'],
            ['time' => '09:00', 'description' => 'Silop Cave exploration'],
            ['time' => '11:30', 'description' => 'Looc Pebble Beach stop'],
            ['time' => '12:30', 'description' => 'Packed lunch'],
            ['time' => '14:30', 'description' => 'Return to Surigao City'],
        ],
    ];

    public function run(): void
    {
        $dayOffsets = [0, 2, 5, 7, 10, 14, 21, 28];

        foreach (array_keys($this->itineraries) as $name) {
            $package = Package::query()->where('package_name', $name)->first();

            if ($package === null) {
                continue;
            }

            $wanted = $this->pickupNames[$name];
            $locations = PickupLocation::query()
                ->where('status', 'active')
                ->whereIn('name', $wanted)
                ->get()
                ->sortBy(fn (PickupLocation $location): int|false => array_search($location->name, $wanted, true))
                ->values();

            $itinerary = $this->itineraries[$name];

            foreach ($dayOffsets as $offset) {
                $tourDate = TourDate::updateOrCreate(
                    [
                        'package_id' => $package->id,
                        'tour_date' => Carbon::today()->addDays($offset)->format('Y-m-d'),
                    ],
                    [
                        'capacity' => 25,
                    ]
                );

                if ($tourDate->bookings()->exists()) {
                    continue;
                }

                $tourDate->times()->delete();

                foreach ($itinerary as $item) {
                    $tourDate->times()->create([
                        'time' => $item['time'],
                        'description' => $item['description'],
                    ]);
                }

                $tourDate->pickupSchedules()->delete();

                $firstTime = Carbon::createFromFormat('H:i', $itinerary[0]['time']);

                foreach ($locations as $locIndex => $location) {
                    $minutesBefore = max(15, 60 - ($locIndex * 15));

                    $tourDate->pickupSchedules()->create([
                        'pickup_location_id' => $location->id,
                        'pickup_time' => $firstTime->copy()->subMinutes($minutesBefore)->format('H:i'),
                    ]);
                }

                WeatherForecast::updateOrCreate(
                    [
                        'tour_date_id' => $tourDate->id,
                        'forecast_date' => $tourDate->tour_date,
                    ],
                    [
                        'location' => $package->destination,
                        'latitude' => $package->latitude,
                        'longitude' => $package->longitude,
                        'temperature_min' => 25.0,
                        'temperature_max' => 32.0,
                        'temperature_current' => 30.0,
                        'weather_condition' => 'Partly Cloudy',
                        'weather_code' => 1,
                        'rainfall_probability' => 15,
                        'rainfall' => 0.0,
                        'wind_speed' => 12.0,
                        'wind_gusts' => 18.0,
                        'wind_direction' => 70,
                        'humidity' => 76,
                        'advisory_level' => 'green',
                        'advisory_badge' => 'Normal / Suitable',
                        'advisory_message' => 'Calm coastal conditions around Surigao City. Suitable for island hopping and cave tours.',
                        'is_outdated' => false,
                        'fetched_at' => now(),
                    ]
                );
            }
        }
    }
}
