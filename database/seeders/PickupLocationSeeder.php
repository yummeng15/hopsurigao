<?php

namespace Database\Seeders;

use App\Models\PickupLocation;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PickupLocationSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $locations = [
            [
                'name' => 'Dapa Port Departure Hub',
                'address' => 'Port Road, Dapa, Siargao Island, Surigao del Norte',
                'latitude' => 9.7574000,
                'longitude' => 126.0528000,
                'description' => 'Main maritime hub with passenger waiting lounge and boat dock access.',
                'status' => 'active',
            ],

            [
                'name' => 'General Luna Tourism Boulevard',
                'address' => 'Tourism Road, General Luna, Siargao Island, Surigao del Norte',
                'latitude' => 9.8004000,
                'longitude' => 126.1622000,
                'description' => 'Central pickup point located in front of major resorts and homestays.',
                'status' => 'active',
            ],

            [
                'name' => 'General Luna Public Market Pier',
                'address' => 'Public Market Wharf, General Luna, Siargao Island, Surigao del Norte',
                'latitude' => 9.8032000,
                'longitude' => 126.1601000,
                'description' => 'Direct jump-off point for Tri-Island and coastal boat tours.',
                'status' => 'active',
            ],

            [
                'name' => 'Del Carmen Municipal Tourism Center',
                'address' => 'Del Carmen Port Complex, Del Carmen, Siargao Island, Surigao del Norte',
                'latitude' => 9.8688000,
                'longitude' => 126.0264000,
                'description' => 'Official registration and boat terminal for Sugba Lagoon & mangrove expeditions.',
                'status' => 'active',
            ],

            [
                'name' => 'Cloud 9 Boardwalk Entrance',
                'address' => 'Cloud 9 Surfing Area, Catangnan, General Luna, Surigao del Norte',
                'latitude' => 9.8087000,
                'longitude' => 126.1680000,
                'description' => 'Meeting station for land tours and north island excursions.',
                'status' => 'active',
            ],

            [
                'name' => 'Surigao City Boulevard Wharf',
                'address' => 'Boulevard, Surigao City, Surigao del Norte',
                'latitude' => 9.7858000,
                'longitude' => 125.4942000,
                'description' => 'City boat terminal for Basul, Hikdop, and other Surigao City island departures.',
                'status' => 'active',
            ],

            [
                'name' => 'Mabua Pebble Beach Station',
                'address' => 'Brgy. Mabua, Surigao City, Surigao del Norte',
                'latitude' => 9.8100000,
                'longitude' => 125.4390000,
                'description' => 'Shore meeting point for Mabua Pebble Beach and nearby coastal tours.',
                'status' => 'active',
            ],

            [
                'name' => 'Sayak Airport Terminal',
                'address' => 'Sayak Airport, Del Carmen, Siargao Island, Surigao del Norte',
                'latitude' => 9.8591000,
                'longitude' => 126.0140000,
                'description' => 'Airport arrival station available for pre-arranged private shuttle pickups.',
                'status' => 'inactive',
            ],
        ];

        foreach ($locations as $location) {
            PickupLocation::updateOrCreate(
                ['name' => $location['name']],
                [
                    'address' => $location['address'],
                    'latitude' => $location['latitude'],
                    'longitude' => $location['longitude'],
                    'description' => $location['description'],
                    'status' => $location['status'],
                ]
            );
        }
    }
}
