<?php

namespace Database\Seeders;

use App\Models\Package;
use App\Models\PickupLocation;
use App\Models\TourDate;
use App\Models\WeatherForecast;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class TourDateSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Itinerary templates keyed by package name.
     */
    private array $itineraries = [
        'Siargao Tri-Island Hopping' => [
            ['time' => '07:00', 'description' => 'Tourist Registration & Safety Briefing'],
            ['time' => '07:30', 'description' => 'Boat Departure from General Luna Wharf'],
            ['time' => '08:00', 'description' => 'Guyam Island Exploration & Coconut Refreshments'],
            ['time' => '10:30', 'description' => 'Daku Island Swimming & Seafood Picnic Lunch'],
            ['time' => '13:30', 'description' => 'Naked Island Sandbar & Sunbathing'],
            ['time' => '15:30', 'description' => 'Scenic Cruise back to General Luna'],
            ['time' => '16:30', 'description' => 'Tour Concludes & Passenger Disembarkation'],
        ],

        'Sugba Lagoon Adventure' => [
            ['time' => '06:30', 'description' => 'Meet-up at Del Carmen Tourism Port'],
            ['time' => '07:00', 'description' => 'Mangrove River Boat Cruise'],
            ['time' => '08:15', 'description' => 'Arrival at Sugba Lagoon Floating House'],
            ['time' => '08:30', 'description' => 'Paddleboarding, Diving Board & Lagoon Swimming'],
            ['time' => '12:00', 'description' => 'Traditional Del Carmen Island Lunch'],
            ['time' => '13:30', 'description' => 'Free Time / Mangrove Photography'],
            ['time' => '15:30', 'description' => 'Return Boat Trip to Del Carmen Port'],
            ['time' => '16:45', 'description' => 'Arrival & Tour Wrap-up'],
        ],

        'Sohoton Bucas Grande Adventure' => [
            ['time' => '05:30', 'description' => 'Early Assembly at Dapa Departure Pier'],
            ['time' => '06:00', 'description' => 'Large Outrigger Boat Departure'],
            ['time' => '08:00', 'description' => 'Arrival at Sohoton PAMB Reception Center'],
            ['time' => '08:30', 'description' => 'Hagukan Cave & Diving Cave Expedition'],
            ['time' => '10:30', 'description' => 'Jellyfish Sanctuary Paddleboat Tour'],
            ['time' => '12:30', 'description' => 'Island Seafood Lunch Feast'],
            ['time' => '14:00', 'description' => 'Club Tara Scenic Cruise & Tiktikan Lake'],
            ['time' => '15:45', 'description' => 'Return Journey across Bucas Grande Passage'],
            ['time' => '18:00', 'description' => 'Arrival at Dapa Port'],
        ],

        'Magpupungko Rock Pools Explorer' => [
            ['time' => '06:00', 'description' => 'Van Pickup from Tourism Hubs'],
            ['time' => '06:30', 'description' => 'Scenic Highway Land Transfer to Pilar'],
            ['time' => '07:45', 'description' => 'Arrival at Magpupungko Beach & Tide Check'],
            ['time' => '08:00', 'description' => 'Low-Tide Tidal Pool Swimming & Cliff Jumps'],
            ['time' => '11:30', 'description' => 'Beachside Boodle Fight Lunch'],
            ['time' => '13:00', 'description' => 'Pacifico Beach / Siargao Lighthouse Stop'],
            ['time' => '15:30', 'description' => 'Return Drive to General Luna'],
            ['time' => '16:45', 'description' => 'Tour Ends'],
        ],

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

        'Siargao Island Land & Sea Tour' => [
            ['time' => '06:30', 'description' => 'Hotel Pickup & Morning Registration'],
            ['time' => '07:00', 'description' => 'Coconut Mountain Viewpoint Photo Session'],
            ['time' => '08:30', 'description' => 'Maasin River Bent Tree & Rope Swing'],
            ['time' => '11:00', 'description' => 'Tayangban Cave Pool Spelunking'],
            ['time' => '12:30', 'description' => 'Island Bistro Lunch in Pilar'],
            ['time' => '14:30', 'description' => 'Catangnan Bridge & Sunset Viewing at Cloud 9'],
            ['time' => '17:30', 'description' => 'Drop-off at Accommodations'],
        ],
    ];

    /**
     * Mainland Surigao del Norte tours meet in Surigao City, not on Siargao.
     *
     * @var array<string, list<string>>
     */
    private array $pickupNames = [
        'Surigao City Island Hopping' => [
            'Surigao City Boulevard Wharf',
            'Mabua Pebble Beach Station',
        ],
        'Silop Cave Explorer' => [
            'Surigao City Boulevard Wharf',

        $weatherPresets = [
            [
                'condition' => 'Clear & Sunny',
                'code' => 0,
                'min' => 25.0,
                'max' => 32.5,
                'rain_prob' => 5,
                'wind' => 10.2,
                'level' => 'green',
                'badge' => 'Normal / Suitable',
                'msg' => 'Calm seas and optimal visibility. Ideal for all maritime activities and island hopping.',
            ],
            [
                'condition' => 'Partly Cloudy',
                'code' => 1,
                'min' => 24.5,
                'max' => 31.0,
                'rain_prob' => 15,
                'wind' => 13.5,
                'level' => 'green',
                'badge' => 'Normal / Suitable',
                'msg' => 'Good maritime conditions with gentle tropical breeze.',
            ],
            [
                'condition' => 'Scattered Showers',
                'code' => 61,
                'min' => 24.0,
                'max' => 29.5,
                'rain_prob' => 45,
                'wind' => 22.0,
                'level' => 'yellow',
                'badge' => 'Caution / Monitor',
                'msg' => 'Passing localized rain showers. Tours proceed with standard precautions; life vests mandatory.',
            ],
            [
                'condition' => 'Moderate Choppy Seas',
                'code' => 63,
                'min' => 23.5,
                'max' => 28.0,
                'rain_prob' => 60,
                'wind' => 28.5,
                'level' => 'yellow',
                'badge' => 'Caution / Monitor',
                'msg' => 'Slightly choppy waters in open channels. Extra sea vigilance advised.',
            ],
            [
                'condition' => 'Gale Warning / Rough Seas',
                'code' => 80,
                'min' => 22.0,
                'max' => 26.5,
                'rain_prob' => 85,
                'wind' => 42.0,
                'level' => 'orange',
                'badge' => 'Warning / Gale',
                'msg' => 'Rough maritime conditions in eastern littoral waters. Small sea craft warned against venturing.',
            ],
        ];

        // Clean any past tour dates and forecasts
        TourDate::query()
            ->where('tour_date', '<', Carbon::today()->format('Y-m-d'))
            ->delete();

        Package::query()
            ->where('status', 'active')
            ->get()
            ->each(function (Package $package, $pkgIndex) use ($pickupLocations, $dayOffsets, $weatherPresets) {
                $itinerary = $this->itineraries[$package->package_name] ?? $this->defaultItinerary;

                foreach ($dayOffsets as $idx => $offset) {
                    $tourDateStr = Carbon::today()->addDays($offset)->format('Y-m-d');

                    $tourDate = TourDate::updateOrCreate(
                        [
                            'package_id' => $package->id,
                            'tour_date'  => $tourDateStr,
                        ],
                        [
                            'capacity'   => 25,
                        ]
                    );

                    // Re-seed Itinerary times
                    $tourDate->times()->delete();
                    foreach ($itinerary as $item) {
                        $tourDate->times()->create([
                            'time'        => $item['time'],
                            'description' => $item['description'],
                        ]);
                    }

                    // Re-seed Pickup Schedules
                    $firstTime = Carbon::createFromFormat('H:i', $itinerary[0]['time'] ?? '06:00');

                    $tourDate->pickupSchedules()->delete();
                    foreach ($pickupLocations->values() as $locIndex => $location) {
                        $minutesBefore = max(15, 60 - ($locIndex * 15));
                        $pickupTime = $firstTime->copy()->subMinutes($minutesBefore)->format('H:i');

                        $tourDate->pickupSchedules()->create([
                            'pickup_location_id' => $location->id,
                            'pickup_time'        => $pickupTime,
                        ]);
                    }

                    // Seed Weather Forecast for this Tour Date
                    $weatherIndex = ($pkgIndex + $idx) % count($weatherPresets);
                    $preset = $weatherPresets[$weatherIndex];

                    WeatherForecast::updateOrCreate(
                            'tour_date' => $tourDateStr,
                        ],
                        [
                            'capacity' => 25,
                        ]
                    );

                    // Re-seed Itinerary times
                    $tourDate->times()->delete();
                    foreach ($itinerary as $item) {
                        $tourDate->times()->create([
                            'time' => $item['time'],
                            'description' => $item['description'],
                        ]);
                    }

                    // Re-seed Pickup Schedules
                    $firstTime = Carbon::createFromFormat('H:i', $itinerary[0]['time'] ?? '06:00');

                    $tourDate->pickupSchedules()->delete();
                    foreach ($locations->values() as $locIndex => $location) {
                        $minutesBefore = max(15, 60 - ($locIndex * 15));
                        $pickupTime = $firstTime->copy()->subMinutes($minutesBefore)->format('H:i');

                        $tourDate->pickupSchedules()->create([
                            'pickup_location_id' => $location->id,
                            'pickup_time' => $pickupTime,
                        ]);
                    }

                    // Seed Weather Forecast for this Tour Date
                    $weatherIndex = ($pkgIndex + $idx) % count($weatherPresets);
                    $preset = $weatherPresets[$weatherIndex];

                    WeatherForecast::updateOrCreate(
                        [
                            'tour_date_id' => $tourDate->id,
                            'forecast_date' => $tourDate->tour_date,
                        ],
                        [
                            'location' => $package->destination,
                            'latitude' => $package->latitude ?? 9.8583,
                            'longitude' => $package->longitude ?? 126.0461,
                            'temperature_min' => $preset['min'],
                            'temperature_max' => $preset['max'],
                            'temperature_current' => $preset['max'] - 2.0,
                            'weather_condition' => $preset['condition'],
                            'weather_code' => $preset['code'],
                            'rainfall_probability' => $preset['rain_prob'],
                            'rainfall' => $preset['rain_prob'] > 30 ? 2.5 : 0.0,
                            'wind_speed' => $preset['wind'],
                            'wind_gusts' => $preset['wind'] + 8.0,
                            'wind_direction' => 75,
                            'humidity' => 78,
                            'advisory_level' => $preset['level'],
                            'advisory_badge' => $preset['badge'],
                            'advisory_message' => $preset['msg'],
                            'is_outdated' => false,
                            'fetched_at' => now(),
                        ]
                    );
                }
            });
    }
}