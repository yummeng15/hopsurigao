<?php

namespace Database\Seeders;

use App\Models\Package;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PackageSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $packages = [
            [
                'package_name' => 'Siargao Tri-Island Hopping',
                'boat_name' => 'Daku Bangka',
                'destination' => 'Daku Island, Guyam Island & Naked Island',
                'latitude' => 9.8583000,
                'longitude' => 126.0461000,
                'description' => 'Experience the famous Siargao island-hopping adventure featuring Daku Island, Guyam Island, and Naked Island. Enjoy swimming, sightseeing, beach activities, and a relaxing tropical island experience.',
                'price' => 1500.00,
                'status' => 'active',

                'inclusions' => [
                    'Boat transportation with licensed captain',
                    'Local certified tour guide',
                    'Island hopping fees and permits',
                    'Full picnic seafood lunch on Daku Island',
                    'Unlimited mineral drinking water',
                    'Cottage rental and beachfront access',
                    'Standard life vests and safety gear',
                    'All environmental and docking fees',
                ],

                'reminders' => [
                    'Bring extra clothes and dry bags',
                    'Apply eco-friendly reef-safe sunscreen',
                    'Wear comfortable swimwear and aqua shoes',
                    'Bring personal medication if prone to motion sickness',
                    'Follow the tour guide and local Coast Guard safety guidelines',
                ],
            ],

            [
                'package_name' => 'Sugba Lagoon Adventure',
                'destination' => 'Sugba Lagoon, Del Carmen',
                'latitude' => 9.8700000,
                'longitude' => 125.9800000,
                'description' => 'Explore the breathtaking emerald waters of Sugba Lagoon in Del Carmen, Siargao. Paddleboard across tranquil mangroves, cliff dive from the wooden springboard, and immerse in pristine nature.',
                'price' => 2000.00,
                'status' => 'active',

                'inclusions' => [
                    'Roundtrip boat transfer from Del Carmen Port',
                    'Tour guide and lagoon coordinator',
                    'Municipal environmental & entrance fees',
                    'Standard life vests and safety briefing',
                    'Complimentary mineral water and refreshments',
                    'Freshly prepared local buffet lunch',
                    'Access to diving board and floating pontoon',
                ],

                'reminders' => [
                    'Bring extra clothes and towel',
                    'Single-use plastic bottles are strictly prohibited',
                    'Follow designated lagoon swimming zones',
                    'Keep personal electronics in waterproof pouches',
                    'Respect local mangrove wildlife',
                ],
            ],

            [
                'package_name' => 'Sohoton Bucas Grande Adventure',
                'destination' => 'Sohoton Cove, Bucas Grande',
                'latitude' => 9.6167000,
                'longitude' => 125.9167000,
                'description' => 'Discover the otherworldly lagoons, caves, and stingless jellyfish sanctuary of Sohoton Cove. Navigate through tidal tunnels surrounded by majestic karst limestone cliffs.',
                'price' => 3000.00,
                'status' => 'active',

                'inclusions' => [
                    'Large outrigger boat transportation',
                    'Registered boat captain and tour guides',
                    'Small paddle boat for Hagukan & Magkukuob Caves',
                    'Jellyfish Sanctuary entry and paddleboat',
                    'All PAMB and municipal entrance fees',
                    'Full hearty lunch and drinking water',
                    'Safety helmets and certified life vests',
                ],

                'reminders' => [
                    'Cave entry depends on high/low tide levels',
                    'Do not touch or lift stingless jellyfish out of the water',
                    'Wear sturdy footwear or aqua shoes',
                    'Long boat ride — bring light jacket or windbreaker',
                    'Follow strict conservation protocols',
                ],
            ],

            [
                'package_name' => 'Magpupungko Rock Pools Explorer',
                'destination' => 'Magpupungko Rock Pools, Pilar',
                'latitude' => 9.8833000,
                'longitude' => 126.1333000,
                'description' => 'Explore the famous natural tidal rock pools of Magpupungko in Pilar, Siargao. Marvel at the balancing rock formation, dive into translucent crystal pools, and relax on cream sand beaches.',
                'price' => 1800.00,
                'status' => 'active',

                'inclusions' => [
                    'Air-conditioned land transportation / van',
                    'Tour coordinator and safety guide',
                    'Magpupungko entrance and environmental fees',
                    'Hydration kit and mineral water',
                    'Local island boodle fight lunch',
                    'First aid and emergency assistance kit',
                ],

                'reminders' => [
                    'Tours are synchronized precisely with low-tide schedules',
                    'Aqua shoes or reef booties are highly recommended',
                    'Bring sun protection and extra swim gear',
                    'Exercise caution when walking on wet rock formations',
                ],
            ],

            [
                'package_name' => 'Siargao Island Land & Sea Tour',
                'destination' => 'Coconut Mountain View, Maasin River & Cloud 9',
                'latitude' => 9.7900000,
                'longitude' => 126.1500000,
                'description' => 'Experience the best land highlights of Siargao including the iconic Coconut Tree Viewpoint, the bent coconut tree rope swing at Maasin River, Pacifico beaches, and sunset surfing at Cloud 9.',
                'price' => 2500.00,
                'status' => 'active',

                'inclusions' => [
                    'Comfortable land tour van transfers',
                    'Experienced local driver & guide',
                    'Maasin River boat and rope swing fees',
                    'Entrance fees to all listed viewpoints',
                    'Complimentary island refreshments and lunch',
                    'Photo and video assistance',
                ],

                'reminders' => [
                    'Wear comfortable walking shoes and casual beachwear',
                    'Charge your phones and cameras for scenic photo spots',
                    'Bring small cash for local souvenir stands',
                    'Stay hydrated throughout the land expedition',
                ],
            ],

            [
                'package_name' => 'Siargao Sunset Coastal Discovery',
                'destination' => 'General Luna & Nearby Coastal Reefs',
                'latitude' => 9.7800000,
                'longitude' => 126.1600000,
                'description' => 'A peaceful late afternoon coastal cruise designed for travelers who want to enjoy golden hour sunset views, calm seas, and light refreshments around General Luna.',
                'price' => 1200.00,
                'status' => 'inactive',

                'inclusions' => [
                    'Sunset catamaran / boat cruise',
                    'Onboard music and light snack platter',
                    'Chilled fresh coconut and beverages',
                    'Life jackets and safety assistance',
                ],

                'reminders' => [
                    'Departs promptly at 16:30 for sunset viewing',
                    'Bring camera for golden hour photography',
                    'Casual resort wear recommended',
                ],
            ],

            [
                'package_name' => 'Surigao City Island Hopping',
                'destinations' => [
                    'Basul Island',
                    'Hikdop Island',
                    'Buenavista Cave',
                ],
                'boat_name' => 'Basul Bangka',
                'latitude' => 9.7700000,
                'longitude' => 125.5600000,
                'description' => 'Island hopping from Surigao City, Surigao del Norte. Swim on Basul Island, relax at Pagkawasan Beach on Hikdop Island, and explore the limestone chambers of Buenavista Cave.',
                'price' => 2200.00,
                'status' => 'active',

                'inclusions' => [
                    'Boat transfer from Surigao City Boulevard Wharf',
                    'Licensed boat captain and local guide',
                    'Basul Island and Hikdop Island landing fees',
                    'Buenavista Cave guide, helmet, and lamp',
                    'Picnic lunch and drinking water',
                    'Life vests and safety briefing',
                ],

                'reminders' => [
                    'Wear aqua shoes for pebble shores and cave floors',
                    'Cave paths can be slippery after rain',
                    'Bring a dry bag for phones and extra clothes',
                    'Follow the guide inside Buenavista Cave',
                ],
            ],

            [
                'package_name' => 'Silop Cave Explorer',
                'destinations' => [
                    'Silop Cave',
                    'Looc Pebble Beach',
                ],
                'latitude' => 9.7405530,
                'boat_name' => 'Silop Bangka',
                'destinations' => [
                    'Silop Cave',
                    'Looc Pebble Beach',
                ],
                'latitude' => 9.7405530,
                'longitude' => 125.5150110,
                'description' => 'A cave and coast tour in Surigao City, Surigao del Norte. Walk the chambers of Silop Cave with a trained guide, then cool down at Looc Pebble Beach.',
                'price' => 1500.00,
                'status' => 'active',

                'inclusions' => [
                    'Roundtrip transfer from Surigao City',
                    'Registered Silop Cave guide',
                    'Helmet, headlamp, and gloves',
                    'Barangay registration and entrance fees',
                    'Looc Pebble Beach access',
                    'Packed lunch and drinking water',
                ],

                'reminders' => [
                    'Wear closed shoes with a good grip',
                    'Tell the guide about knee, heart, or claustrophobia concerns',
                    'Do not touch cave formations',
                    'Bring a change of clothes after the cave',
                ],
            ],
        ];

        foreach ($packages as $data) {
            $destinations = $data['destinations'] ?? [$data['destination']];

            $package = Package::updateOrCreate(
                ['package_name' => $data['package_name']],
                [
                    'boat_name' => $data['boat_name'],
                    'status' => $data['status'],
                ]
            );

            $package->destinations()->delete();

            foreach ($destinations as $position => $name) {
                $package->destinations()->create([
                    'name' => $name,
                    'position' => $position,
                ]);
            }

            // Re-seed inclusions
            $package->inclusions()->delete();
            foreach ($data['inclusions'] as $inclusion) {
                $package->inclusions()->create([
                    'description' => $inclusion,
                ]);
            }

            // Re-seed reminders
            $package->reminders()->delete();
            foreach ($data['reminders'] as $reminder) {
                $package->reminders()->create([
                    'description' => $reminder,
                ]);
            }

            $package->activities()->delete();
            foreach ($this->activitiesFor($data['package_name']) as $activity) {
                $package->activities()->create([
                    'description' => $activity,
                ]);
            }
        }
    }

    /**
     * @return list<string>
     */
    private function activitiesFor(string $packageName): array
    {
        return match ($packageName) {
            'Siargao Tri-Island Hopping' => [
                'Island hopping across three islands',
                'Swimming and beach time',
                'Sightseeing',
                'Photo stops',
                'Picnic lunch on the island',
            ],
            'Sugba Lagoon Adventure' => [
                'Lagoon visit',
                'Paddleboarding',
                'Swimming',
                'Cliff jump from the springboard',
                'Mangrove viewing',
            ],
            'Sohoton Bucas Grande Adventure' => [
                'Cove and lagoon exploration',
                'Cave passage by paddle boat',
                'Jellyfish sanctuary visit',
                'Swimming',
                'Photo stops',
            ],
            'Magpupungko Rock Pools Explorer' => [
                'Rock pool swimming',
                'Beach time',
                'Viewing the rock formation',
                'Photo stops',
            ],
            'Siargao Island Land & Sea Tour' => [
                'Viewpoint stops',
                'River visit and rope swing',
                'Beach time',
                'Photo stops',
            ],
            'Siargao Sunset Coastal Discovery' => [
                'Sunset cruise',
                'Coastal sightseeing',
                'Photo stops',
                'Light snacks on board',
            ],
            'Surigao City Island Hopping' => [
                'Island hopping',
                'Swimming',
                'Cave exploration',
                'Beach time',
                'Photo stops',
            ],
            'Silop Cave Explorer' => [
                'Guided cave walk',
                'Pebble beach time',
                'Photo stops',
            ],
            default => [],
        };
    }
}
