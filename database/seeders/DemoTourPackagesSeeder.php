<?php

namespace Database\Seeders;

use App\Models\Package;
use App\Models\PickupLocation;
use App\Models\TourDate;
use App\Models\TourOperator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Idempotent demo packages for capstone defense.
 *
 * Requires an explicit approved operator id via constructor
 * or DEMO_TOUR_OPERATOR_ID. Never auto-picks an operator.
 *
 * Not called from DatabaseSeeder — run only via:
 * php artisan demo:seed-tour-packages --operator=ID
 */
class DemoTourPackagesSeeder extends Seeder
{
    public const DEMO_MARKER = 'HopSurigao Capstone Demo Package';

    /**
     * @var list<array{package: string, action: string, status: string, reason: string|null, dates: int}>
     */
    public array $report = [];

    public function __construct(
        private readonly ?int $operatorId = null,
        private readonly bool $dryRun = false,
    ) {}

    public function run(): void
    {
        $operator = $this->resolveOperator();

        if ($this->dryRun) {
            $this->command?->info("[dry-run] Target operator: #{$operator->id} {$operator->name} ({$operator->approval_status})");
        }

        $pickups = $this->ensurePickupLocations($operator->id);

        foreach ($this->catalog() as $index => $spec) {
            $this->seedPackage($operator->id, $index, $spec, $pickups);
        }
    }

    private function resolveOperator(): TourOperator
    {
        $id = $this->operatorId ?? (int) env('DEMO_TOUR_OPERATOR_ID', 0);

        if ($id <= 0) {
            throw new RuntimeException(
                'Set DEMO_TOUR_OPERATOR_ID or pass --operator=ID. Refusing to auto-select a tour operator.'
            );
        }

        $operator = TourOperator::query()->find($id);

        if ($operator === null) {
            throw new RuntimeException("Tour operator #{$id} was not found.");
        }

        if ($operator->approval_status !== TourOperator::STATUS_ACTIVE || ! $operator->is_active) {
            throw new RuntimeException(
                "Tour operator #{$id} ({$operator->name}) is not approved/active. Approval={$operator->approval_status}, is_active=".($operator->is_active ? '1' : '0')
            );
        }

        if (! $operator->hasAuthorizedAdmin()) {
            throw new RuntimeException(
                "Tour operator #{$id} ({$operator->name}) has no Admin user assigned."
            );
        }

        return $operator;
    }

    /**
     * @param  array<string, int>  $pickups
     * @param  array<string, mixed>  $spec
     */
    private function seedPackage(int $operatorId, int $index, array $spec, array $pickups): void
    {
        $existing = Package::withoutGlobalScopes()
            ->where('tour_operator_id', $operatorId)
            ->where('package_name', $spec['package_name'])
            ->first();

        if ($this->dryRun) {
            $action = $existing ? 'would-update' : 'would-create';
            $this->report[] = [
                'package' => $spec['package_name'],
                'action' => $action,
                'status' => $existing?->status ?? 'inactive',
                'reason' => null,
                'dates' => count($this->scheduleDates($index)),
            ];
            $this->command?->line("  [dry-run] {$action}: {$spec['package_name']}");

            return;
        }

        $package = DB::transaction(function () use ($existing, $operatorId, $spec, $index, $pickups): Package {
            if ($existing === null) {
                $package = new Package;
                $package->tour_operator_id = $operatorId;
                $package->package_name = $spec['package_name'];
                $package->boat_name = $spec['boat_name'];
                $package->destination = implode(', ', $spec['destinations']);
                $package->description = $spec['description'];
                $package->price = $spec['price'];
                $package->latitude = $spec['latitude'];
                $package->longitude = $spec['longitude'];
                $package->status = 'inactive';
                $package->under_maintenance = false;
                $package->save();
                $action = 'created';
            } else {
                $package = $existing;
                // Preserve existing price; refresh demo metadata only.
                $package->boat_name = $spec['boat_name'];
                $package->destination = implode(', ', $spec['destinations']);
                $package->description = $spec['description'];
                $package->latitude = $spec['latitude'];
                $package->longitude = $spec['longitude'];
                $package->under_maintenance = false;
                $package->save();
                $action = 'updated';
            }

            $this->syncChildren($package, $spec);
            $datesCreated = $this->ensureSchedules($package, $operatorId, $index, $spec, $pickups);

            $issues = $this->publicationIssues($package, $operatorId);
            $reason = null;

            if ($issues === []) {
                if ($package->status !== 'active') {
                    $package->status = 'active';
                    $package->save();
                }
                $status = 'active';
            } else {
                $status = $package->status === 'active' ? 'active' : 'blocked';
                $reason = implode(' ', $issues);
                if ($package->status === 'active' && $issues !== []) {
                    // Leave already-active packages active; report issues for awareness.
                    $status = 'active';
                    $reason = 'Published earlier, but current checks note: '.$reason;
                }
            }

            $this->report[] = [
                'package' => $package->package_name,
                'action' => $action,
                'status' => $status === 'active' ? 'Published' : ($status === 'blocked' ? 'Blocked' : 'Draft'),
                'reason' => $reason,
                'dates' => $datesCreated,
            ];

            return $package;
        });

        $last = $this->report[array_key_last($this->report)];
        $this->command?->line(
            "  {$last['action']}: {$package->package_name} → {$last['status']}".
            ($last['reason'] ? " ({$last['reason']})" : '')
        );
    }

    /**
     * @param  array<string, mixed>  $spec
     */
    private function syncChildren(Package $package, array $spec): void
    {
        $package->destinations()->delete();
        foreach ($spec['destinations'] as $position => $name) {
            $package->destinations()->create([
                'name' => $name,
                'position' => $position,
            ]);
        }

        $package->inclusions()->delete();
        foreach ($spec['inclusions'] as $inclusion) {
            $package->inclusions()->create(['description' => $inclusion]);
        }

        $package->activities()->delete();
        foreach ($spec['activities'] as $activity) {
            $package->activities()->create(['description' => $activity]);
        }

        $package->reminders()->delete();
        foreach ($spec['reminders'] as $reminder) {
            $package->reminders()->create(['description' => $reminder]);
        }
    }

    /**
     * @param  array<string, mixed>  $spec
     * @param  array<string, int>  $pickups
     */
    private function ensureSchedules(
        Package $package,
        int $operatorId,
        int $index,
        array $spec,
        array $pickups
    ): int {
        $pickupKey = $spec['pickup_key'];
        $pickupId = $pickups[$pickupKey] ?? null;

        if ($pickupId === null) {
            throw new RuntimeException("Missing pickup location key [{$pickupKey}] for {$spec['package_name']}.");
        }

        $createdOrEnsured = 0;

        foreach ($this->scheduleDates($index) as $date) {
            $tourDate = TourDate::withoutGlobalScopes()
                ->where('package_id', $package->id)
                ->whereDate('tour_date', $date)
                ->first();

            if ($tourDate === null) {
                $tourDate = TourDate::withoutGlobalScopes()->create([
                    'package_id' => $package->id,
                    'tour_date' => $date,
                    'capacity' => $spec['capacity'],
                    'travel_closed' => false,
                ]);
            } else {
                $hasBookings = $tourDate->bookings()
                    ->whereIn('booking_status', ['pending', 'confirmed'])
                    ->exists();

                if (! $hasBookings) {
                    $tourDate->capacity = $spec['capacity'];
                    // Do not force travel_closed=false if already closed by weather/ops.
                    $tourDate->save();
                }
            }

            $hasBookings = $tourDate->bookings()
                ->whereIn('booking_status', ['pending', 'confirmed'])
                ->exists();

            if (! $hasBookings) {
                $tourDate->times()->delete();
                $tourDate->times()->create([
                    'time' => $spec['departure_time'],
                    'description' => 'Departure',
                ]);

                $tourDate->pickupSchedules()->delete();
                $tourDate->pickupSchedules()->create([
                    'pickup_location_id' => $pickupId,
                    'pickup_time' => $spec['pickup_time'],
                ]);
            }

            $createdOrEnsured++;
        }

        return $createdOrEnsured;
    }

    /**
     * @return list<string>
     */
    private function scheduleDates(int $index): array
    {
        // Stagger packages so the same calendar day is not overloaded across boats.
        $dates = [];
        $startOffset = 2 + ($index % 3);

        for ($week = 0; $week < 5; $week++) {
            $dates[] = Carbon::today()
                ->addDays($startOffset + ($week * 7))
                ->toDateString();
        }

        return $dates;
    }

    /**
     * Mirrors OperatorPackagePublicationController::publicationIssues.
     *
     * @return list<string>
     */
    private function publicationIssues(Package $package, int $operatorId): array
    {
        $issues = [];

        if (trim((string) $package->package_name) === '' ||
            trim((string) $package->boat_name) === '' ||
            trim((string) $package->destination) === '') {
            $issues[] = 'Complete the package name, boat name, and destinations.';
        }

        if ((float) $package->price <= 0) {
            $issues[] = 'Enter a price greater than zero.';
        }

        if ((bool) $package->under_maintenance) {
            $issues[] = 'The boat is under maintenance.';
        }

        $readyDateExists = TourDate::withoutGlobalScopes()
            ->where('package_id', $package->id)
            ->whereDate('tour_date', '>=', today()->toDateString())
            ->where('capacity', '>', 0)
            ->where('travel_closed', false)
            ->whereHas('times')
            ->whereHas('pickupSchedules', function ($query) use ($operatorId): void {
                $query->whereHas('pickupLocation', function ($locations) use ($operatorId): void {
                    $locations->where('status', 'active')
                        ->where(function ($allowed) use ($operatorId): void {
                            $allowed->whereNull('tour_operator_id')
                                ->orWhere('tour_operator_id', $operatorId);
                        });
                });
            })
            ->exists();

        if (! $readyDateExists) {
            $issues[] = 'Create at least one future open tour date with capacity, a departure time, and an active pickup location.';
        }

        return $issues;
    }

    /**
     * Prefer existing shared/active hubs; otherwise create operator-owned hubs.
     * Railway currently has no shared Siargao/Surigao hubs, so operator-owned
     * records are created safely without touching other operators' pickups.
     *
     * @return array<string, int>
     */
    private function ensurePickupLocations(int $operatorId): array
    {
        $map = [];

        foreach ($this->requiredPickupCatalog() as $key => $data) {
            $sharedOrOwnedId = PickupLocation::withoutGlobalScopes()
                ->where('name', $data['name'])
                ->where('status', 'active')
                ->where(function ($query) use ($operatorId): void {
                    $query->whereNull('tour_operator_id')
                        ->orWhere('tour_operator_id', $operatorId);
                })
                ->value('id');

            if ($sharedOrOwnedId !== null) {
                $map[$key] = (int) $sharedOrOwnedId;
                $this->command?->line("  pickup exists: {$data['name']} (#{$sharedOrOwnedId})");

                continue;
            }

            if ($this->dryRun) {
                $map[$key] = 0;
                $this->command?->line("  [dry-run] pickup would-create (operator-owned): {$data['name']}");

                continue;
            }

            $location = new PickupLocation;
            $location->tour_operator_id = $operatorId;
            $location->name = $data['name'];
            $location->address = $data['address'];
            $location->latitude = $data['latitude'];
            $location->longitude = $data['longitude'];
            $location->description = $data['description'];
            $location->status = 'active';
            $location->save();

            $map[$key] = (int) $location->id;
            $this->command?->line("  pickup created: {$data['name']} (#{$location->id})");
        }

        return $map;
    }

    /**
     * @return array<string, array{name: string, address: string, latitude: string, longitude: string, description: string}>
     */
    private function requiredPickupCatalog(): array
    {
        return [
            'general_luna_market' => [
                'name' => 'General Luna Public Market Pier',
                'address' => 'Public Market Wharf, General Luna, Siargao Island, Surigao del Norte',
                'latitude' => '9.8032000',
                'longitude' => '126.1601000',
                'description' => 'Jump-off for General Luna island-hopping routes.',
            ],
            'general_luna_boulevard' => [
                'name' => 'General Luna Tourism Boulevard',
                'address' => 'Tourism Road, General Luna, Siargao Island, Surigao del Norte',
                'latitude' => '9.8004000',
                'longitude' => '126.1622000',
                'description' => 'Central General Luna meeting point for boat tours.',
            ],
            'dapa_port' => [
                'name' => 'Dapa Port Departure Hub',
                'address' => 'Port Road, Dapa, Siargao Island, Surigao del Norte',
                'latitude' => '9.7574000',
                'longitude' => '126.0528000',
                'description' => 'Dapa maritime hub for Mam-on, La Januza, and Corregidor routes.',
            ],
            'del_carmen' => [
                'name' => 'Del Carmen Municipal Tourism Center',
                'address' => 'Del Carmen Port Complex, Del Carmen, Siargao Island, Surigao del Norte',
                'latitude' => '9.8688000',
                'longitude' => '126.0264000',
                'description' => 'Del Carmen port for Kawhagan and Pamomoan boat trips.',
            ],
            'surigao_boulevard' => [
                'name' => 'Surigao City Boulevard Wharf',
                'address' => 'Boulevard, Surigao City, Surigao del Norte',
                'latitude' => '9.7858000',
                'longitude' => '125.4942000',
                'description' => 'City wharf for Basul, Hikdop, and Danawan departures.',
            ],
            'san_benito' => [
                'name' => 'San Benito Tourism Jump-off',
                'address' => 'San Benito Tourism Office area, San Benito, Siargao Island, Surigao del Norte',
                'latitude' => '9.9578000',
                'longitude' => '126.0215000',
                'description' => 'Northern Siargao jump-off for San Benito island and Poneas Lake boat tours.',
            ],
            'socorro_pamosaingan' => [
                'name' => 'Socorro Pamosaingan Port',
                'address' => 'Pamosaingan Port, Socorro, Bucas Grande, Surigao del Norte',
                'latitude' => '9.6215000',
                'longitude' => '125.9668000',
                'description' => 'Mainland jump-off for Sohoton Cove and Bucas Grande lagoon tours.',
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function catalog(): array
    {
        $baseInclusions = [
            'Roundtrip boat transportation',
            'Life jackets for guests',
            'Boat crew assistance during the tour',
            'Pre-departure safety briefing',
        ];

        $baseReminders = [
            'Bring sun protection, a towel, and a dry bag for phones',
            'Wear comfortable swimwear and non-slip footwear',
            'Tour order may change with sea and weather conditions',
            'Follow crew instructions at all times',
        ];

        return [
            [
                'package_name' => 'Siargao Four-Island Adventure',
                'boat_name' => 'Four Island Voyager',
                'destinations' => ['Daku Island', 'Guyam Island', 'Naked Island', 'Corregidor Island (Casulian)'],
                'description' => 'A full-day Siargao island-hopping route covering the classic Daku, Guyam, and Naked Island stops, plus Corregidor (Casulian) for a quieter beach and hill viewpoint. Ideal for first-time visitors who want swimming, photo stops, and boat time in one day.',
                'price' => 2500,
                'capacity' => 60,
                'latitude' => '9.8250000',
                'longitude' => '126.1450000',
                'pickup_key' => 'general_luna_market',
                'pickup_time' => '07:00',
                'departure_time' => '08:00',
                'activities' => ['Island hopping', 'Swimming', 'Beach time', 'Photo stops', 'Light snorkeling when conditions allow'],
                'inclusions' => $baseInclusions,
                'reminders' => $baseReminders,
            ],
            [
                'package_name' => 'Mam-on and La Januza Island Escape',
                'boat_name' => 'Januza Explorer',
                'destinations' => ['Mam-on Island', 'La Januza Island'],
                'description' => 'Travel south of the popular General Luna coastline to Mam-on and La Januza, known for quieter beaches and a reef channel between the islands. Expect a longer boat ride and more time for swimming and shoreline walks.',
                'price' => 2800,
                'capacity' => 60,
                'latitude' => '9.7050000',
                'longitude' => '126.0480000',
                'pickup_key' => 'dapa_port',
                'pickup_time' => '07:00',
                'departure_time' => '08:00',
                'activities' => ['Island hopping', 'Swimming', 'Beach walks', 'Photo stops', 'Reef viewing from the boat'],
                'inclusions' => $baseInclusions,
                'reminders' => array_merge($baseReminders, ['Boat travel time is longer than the standard tri-island route']),
            ],
            [
                'package_name' => 'Corregidor Island Nature Retreat',
                'boat_name' => 'Casulian Breeze',
                'destinations' => ['Corregidor Island (Casulian)'],
                'description' => 'A focused day trip to Corregidor Island (also called Casulian), a less crowded Siargao stop with white sand, clear water, and optional hill trails overlooking nearby islands. Good for guests who prefer one main destination over multi-stop hopping.',
                'price' => 1800,
                'capacity' => 60,
                'latitude' => '9.7480000',
                'longitude' => '126.0550000',
                'pickup_key' => 'dapa_port',
                'pickup_time' => '07:30',
                'departure_time' => '08:30',
                'activities' => ['Beach time', 'Swimming', 'Hill viewpoint walk', 'Photo stops'],
                'inclusions' => $baseInclusions,
                'reminders' => $baseReminders,
            ],
            [
                'package_name' => 'Kawhagan and Pamomoan Beach Discovery',
                'boat_name' => 'Del Carmen Skiff',
                'destinations' => ['Kawhagan Island', 'Pamomoan Beach'],
                'description' => 'Depart from the Del Carmen side of Siargao for Kawhagan Island and Pamomoan Beach, a west-coast alternative to the General Luna tri-island route. Enjoy mangrove approaches, swimming, and scenic palm-lined shores.',
                'price' => 2200,
                'capacity' => 60,
                'latitude' => '9.8550000',
                'longitude' => '125.9650000',
                'pickup_key' => 'del_carmen',
                'pickup_time' => '07:00',
                'departure_time' => '08:00',
                'activities' => ['Island hopping', 'Swimming', 'Beach photography', 'Mangrove viewing from the boat'],
                'inclusions' => $baseInclusions,
                'reminders' => array_merge($baseReminders, ['Meet at Del Carmen port; land transfer from General Luna is not included']),
            ],
            [
                'package_name' => 'Siargao Hidden Islands Expedition',
                'boat_name' => 'Hidden Coast Runner',
                'destinations' => ['Mam-on Island', 'La Januza Island', 'Corregidor Island (Casulian)'],
                'description' => 'An extended Siargao boat day linking Mam-on, La Januza, and Corregidor for guests who already saw Daku, Guyam, and Naked Island. Longer sea time, fewer crowds, and more swimming stops.',
                'price' => 3500,
                'capacity' => 60,
                'latitude' => '9.7200000',
                'longitude' => '126.0500000',
                'pickup_key' => 'general_luna_boulevard',
                'pickup_time' => '06:45',
                'departure_time' => '07:45',
                'activities' => ['Island hopping', 'Swimming', 'Snorkeling when conditions allow', 'Photo stops', 'Beach time'],
                'inclusions' => $baseInclusions,
                'reminders' => array_merge($baseReminders, ['Full-day itinerary; return time depends on sea conditions']),
            ],
            [
                'package_name' => 'Daku Island Tropical Picnic',
                'boat_name' => 'Daku Dayboat',
                'destinations' => ['Daku Island'],
                'description' => 'A relaxed boat trip focused on Daku Island, Siargao’s popular sandbar stop for swimming and shoreline downtime. Suitable for guests who want a shorter, simpler island day.',
                'price' => 1500,
                'capacity' => 60,
                'latitude' => '9.8250000',
                'longitude' => '126.1450000',
                'pickup_key' => 'general_luna_market',
                'pickup_time' => '07:30',
                'departure_time' => '08:30',
                'activities' => ['Beach time', 'Swimming', 'Photo stops', 'Leisure time on the sandbar'],
                'inclusions' => $baseInclusions,
                'reminders' => $baseReminders,
            ],
            [
                'package_name' => 'Guyam and Naked Island Getaway',
                'boat_name' => 'Twin Sandbar Craft',
                'destinations' => ['Guyam Island', 'Naked Island'],
                'description' => 'A compact island-hopping route to Guyam Island and Naked Island for swimming and sandbar photos without the full four-island itinerary. Morning departure from General Luna keeps the day efficient.',
                'price' => 1300,
                'capacity' => 60,
                'latitude' => '9.8200000',
                'longitude' => '126.1500000',
                'pickup_key' => 'general_luna_market',
                'pickup_time' => '07:30',
                'departure_time' => '08:30',
                'activities' => ['Island hopping', 'Swimming', 'Sandbar photos', 'Beach time'],
                'inclusions' => $baseInclusions,
                'reminders' => $baseReminders,
            ],
            [
                'package_name' => 'San Benito Sandbar and Lake Adventure',
                'boat_name' => 'Northern Siargao Bangka',
                'destinations' => ['Dahican Sandbar', 'Pagbasayan Island', 'Poneas Lake'],
                'description' => 'Explore the quieter northern Siargao coast around San Benito, including island stops and a hike to brackish Poneas Lake when tides allow. Designed for guests seeking a less crowded alternative to General Luna tours.',
                'price' => 2400,
                'capacity' => 60,
                'latitude' => '9.9600000',
                'longitude' => '126.0300000',
                'pickup_key' => 'san_benito',
                'pickup_time' => '07:00',
                'departure_time' => '08:00',
                'activities' => ['Island hopping', 'Sandbar visit', 'Swimming', 'Guided hike to Poneas Lake when open', 'Photo stops'],
                'inclusions' => array_merge($baseInclusions, ['Assistance for the Poneas Lake trail when conditions allow']),
                'reminders' => array_merge($baseReminders, [
                    'Wear shoes suitable for a short uphill hike',
                    'Lake access depends on tide and trail conditions',
                ]),
            ],
            [
                'package_name' => 'Basul Island Beach Escape',
                'boat_name' => 'Basul City Hopper',
                'destinations' => ['Basul Island'],
                'description' => 'A short boat ride from Surigao City Boulevard to Basul Island for swimming and beach time. Convenient for guests staying in the city who want a half-day coastal break.',
                'price' => 1200,
                'capacity' => 60,
                'latitude' => '9.7900000',
                'longitude' => '125.5250000',
                'pickup_key' => 'surigao_boulevard',
                'pickup_time' => '07:30',
                'departure_time' => '08:30',
                'activities' => ['Beach time', 'Swimming', 'Photo stops'],
                'inclusions' => $baseInclusions,
                'reminders' => $baseReminders,
            ],
            [
                'package_name' => 'Hikdop Island Cave and Beach Tour',
                'boat_name' => 'Hikdop Islander',
                'destinations' => ['Hikdop Island', 'Pagkawasan Beach', 'Buenavista Cave'],
                'description' => 'Boat from Surigao City to Hikdop Island for Pagkawasan Beach swimming and a visit to Buenavista Cave’s limestone chambers. Combines shoreline time with a short cave walk.',
                'price' => 2000,
                'capacity' => 60,
                'latitude' => '9.7550000',
                'longitude' => '125.5550000',
                'pickup_key' => 'surigao_boulevard',
                'pickup_time' => '07:00',
                'departure_time' => '08:00',
                'activities' => ['Island hopping', 'Beach swimming', 'Cave walk', 'Photo stops'],
                'inclusions' => array_merge($baseInclusions, ['Basic cave walk assistance']),
                'reminders' => array_merge($baseReminders, [
                    'Cave floors can be slippery; wear non-slip footwear',
                    'Tell the crew about mobility or claustrophobia concerns before entering the cave',
                ]),
            ],
            [
                'package_name' => 'Danawan Island Discovery',
                'boat_name' => 'Danawan Fisherfolk',
                'destinations' => ['Danawan Island'],
                'description' => 'Visit Danawan Island, a Surigao City fishing community about 45 minutes by pump boat from the boulevard wharf. Experience coastal village scenery, swimming spots, and a quieter pace than the main tourist islands.',
                'price' => 2200,
                'capacity' => 60,
                'latitude' => '9.8050000',
                'longitude' => '125.5750000',
                'pickup_key' => 'surigao_boulevard',
                'pickup_time' => '07:00',
                'departure_time' => '08:00',
                'activities' => ['Coastal village visit', 'Swimming', 'Photo stops', 'Beach time'],
                'inclusions' => $baseInclusions,
                'reminders' => array_merge($baseReminders, ['Respect local fishing community spaces and shoreline homes']),
            ],
            [
                'package_name' => 'Sohoton Cave and Lagoon Expedition',
                'boat_name' => 'Bucas Grande Voyager',
                'destinations' => ['Sohoton Cove', 'Hagukan Cave', 'Magkukuob Cave', 'Jellyfish Sanctuary'],
                'description' => 'A Bucas Grande day tour from Socorro’s Pamosaingan Port into Sohoton Cove’s lagoons and tidal cave passages, with a stop at the stingless jellyfish sanctuary when open. Cave access depends on tide levels.',
                'price' => 3200,
                'capacity' => 60,
                'latitude' => '9.6167000',
                'longitude' => '125.9167000',
                'pickup_key' => 'socorro_pamosaingan',
                'pickup_time' => '07:00',
                'departure_time' => '08:00',
                'activities' => ['Lagoon cruising', 'Cave passage by small boat when tide allows', 'Jellyfish sanctuary visit when open', 'Swimming', 'Photo stops'],
                'inclusions' => array_merge($baseInclusions, ['Small boat support for cave passages when accessible']),
                'reminders' => array_merge($baseReminders, [
                    'Cave entry depends on tide; some passages may close on the day of travel',
                    'Do not touch or lift jellyfish in the sanctuary',
                ]),
            ],
        ];
    }
}
