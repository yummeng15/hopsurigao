<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePackageRequest;
use App\Http\Requests\Admin\UpdatePackageRequest;
use App\Models\Package;
use App\Models\PackageDestination;
use App\Models\PickupLocation;
use App\Models\TourDate;
use App\Services\FileUploadService;
use App\Services\UploadPath;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PackageController extends Controller
{
    public function __construct(
        private FileUploadService $files
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Index
    |--------------------------------------------------------------------------
    */

    public function index(): Response
    {
        return Inertia::render(
            'admin/packages/Index'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | DataTable
    |--------------------------------------------------------------------------
    */

    public function getData(Request $request): JsonResponse
    {
        $query = Package::query()
            ->with(['tourOperator.users.role'])
            ->withCount('tourDates');

        if ($search = $request->input('search')) {
            $query->where(function ($query) use ($search) {
                $query
                    ->where(
                        'package_name',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'destination',
                        'like',
                        "%{$search}%"
                    );
            });
        }

        $allowedSorts = [
            'id',
            'package_name',
            'destination',
            'price',
            'status',
            'created_at',
        ];

        $sort = $request->input('sort');

        if (
            $sort &&
            in_array(
                $sort,
                $allowedSorts,
                true
            )
        ) {
            $order = $request->input(
                'order',
                'asc'
            );

            if (
                ! in_array(
                    $order,
                    ['asc', 'desc'],
                    true
                )
            ) {
                $order = 'asc';
            }

            $query->orderBy(
                $sort,
                $order
            );
        } else {
            $query->latest();
        }

        $perPage = (int) $request->input(
            'per_page',
            10
        );

        $perPage = min(
            max($perPage, 1),
            100
        );

        return response()->json(
            $query
                ->paginate($perPage)
                ->through(
                    function (Package $package) {
                        $assignment = $package->assignmentSummary();

                        return [
                            'id' => $package->id,
                            'package_name' => $package->package_name,
                            'destination' => $package->destination,
                            'price' => $package->price,
                            'status' => $package->status,
                            'tour_dates_count' => $package->tour_dates_count,
                            'operator_name' => $assignment['operator_name'] ?? '—',
                            'assignment_status' => $assignment['assignment_status'],
                        ];
                    }
                )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Create
    |--------------------------------------------------------------------------
    */

    public function create(): Response
    {
        return Inertia::render(
            'admin/packages/Create',
            [
                'pickupLocations' => PickupLocation::query()
                    ->where('status', 'active')
                    ->select('id', 'name')
                    ->orderBy('name')
                    ->get(),

                'destinationOptions' => PackageDestination::pickerOptions(),
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Store
    |--------------------------------------------------------------------------
    */

    public function store(
        StorePackageRequest $request
    ): RedirectResponse {

        $data = $request->validated();

        /*
         * Create the database record first.
         *
         * We need the package ID before
         * creating the filesystem directory.
         */
        $package = Package::create(
            collect($data)
                ->only([
                    'package_name',
                    'boat_name',
                    'destination',
                    'description',
                    'price',
                    'status',
                ])
                ->all()
        );

        /*
         * Store package images.
         *
         * NOTHING is saved to the packages table.
         */
        if (! empty($data['images'])) {

            $this->files->storeMany(
                $data['images'],
                UploadPath::package(
                    $package->id
                )
            );
        }

        $this->syncRelations(
            $package,
            $data
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Package created successfully.',
        ]);

        return redirect()->route(
            'admin.packages.index'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Edit
    |--------------------------------------------------------------------------
    */

    public function edit(Package $package): Response
    {
        $package->load([
            'destinations',
            'inclusions',
            'activities',
            'reminders',
            'tourDates' => fn ($query) => $query->withCount('bookings'),
            'tourDates.times',
            'tourDates.pickupSchedules' => fn ($query) => $query->withCount('bookings'),
        ]);

        return Inertia::render(
            'admin/packages/Edit',
            [
                'package' => $package,

                'images' => $this->files->images(
                    UploadPath::package($package->id)
                ),

                'pickupLocations' => PickupLocation::query()
                    ->where('status', 'active')
                    ->select('id', 'name')
                    ->orderBy('name')
                    ->get(),

                'destinationOptions' => PackageDestination::pickerOptions(),
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Update
    |--------------------------------------------------------------------------
    */

    public function update(
        UpdatePackageRequest $request,
        Package $package
    ): RedirectResponse {

        $data = $request->validated();

        /*
        |--------------------------------------------------------------------------
        | Protect Tour Dates & Pickup Schedules With Existing Bookings
        |--------------------------------------------------------------------------
        */

        $incomingDates = collect($data['tour_dates'] ?? [])
            ->pluck('tour_date')
            ->map(fn ($date) => Carbon::parse($date)->format('Y-m-d'))
            ->all();

        $protectedDates = $package->tourDates()
            ->withCount('bookings')
            ->get()
            ->filter(fn ($tourDate) => $tourDate->bookings_count > 0)
            ->filter(fn ($tourDate) => ! in_array(
                $tourDate->tour_date->format('Y-m-d'),
                $incomingDates,
                true
            ))
            ->pluck('tour_date')
            ->map(fn ($date) => $date->format('M d, Y'));

        if ($protectedDates->isNotEmpty()) {
            return back()
                ->withErrors([
                    'tour_dates' => 'These tour dates have existing bookings and cannot be removed: '
                        .$protectedDates->join(', '),
                ])
                ->withInput();
        }

        $incomingPickupLocationsByDate = [];
        foreach ($data['tour_dates'] ?? [] as $td) {
            $date = Carbon::parse($td['tour_date'])->format('Y-m-d');
            $incomingPickupLocationsByDate[$date] = collect($td['pickup_schedules'] ?? [])
                ->pluck('pickup_location_id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        $protectedPickups = $package->tourDates()
            ->with(['pickupSchedules' => fn ($q) => $q->withCount('bookings')->with('pickupLocation')])
            ->get()
            ->flatMap(function ($tourDate) use ($incomingPickupLocationsByDate) {
                $dateStr = $tourDate->tour_date->format('Y-m-d');
                $incomingLocs = $incomingPickupLocationsByDate[$dateStr] ?? [];

                return $tourDate->pickupSchedules
                    ->filter(fn ($ps) => $ps->bookings_count > 0 && ! in_array((int) $ps->pickup_location_id, $incomingLocs, true))
                    ->map(fn ($ps) => ($ps->pickupLocation?->name ?? 'Pickup Location').' on '.$tourDate->tour_date->format('M d, Y'));
            });

        if ($protectedPickups->isNotEmpty()) {
            return back()
                ->withErrors([
                    'tour_dates' => 'These pickup schedules have existing bookings and cannot be removed: '
                        .$protectedPickups->join(', '),
                ])
                ->withInput();
        }

        /*
    |--------------------------------------------------------------------------
    | Update Package Fields
    |--------------------------------------------------------------------------
    */

        $package->fill(
            collect($data)
                ->only([
                    'package_name',
                    'boat_name',
                    'destination',
                    'description',
                    'price',
                    'status',
                ])
                ->all()
        );

        /*
     * Remove selected existing images.
     */
        if (! empty($data['removed_images'])) {

            $directory = UploadPath::package(
                $package->id
            );

            $paths = collect(
                $data['removed_images']
            )
                ->map(
                    fn (string $filename) => $directory.'/'.basename($filename)
                )
                ->all();

            $this->files->deleteMany(
                $paths
            );
        }

        /*
     * Store newly uploaded images.
     */
        if (! empty($data['images'])) {

            $this->files->storeMany(
                $data['images'],
                UploadPath::package(
                    $package->id
                )
            );
        }

        $package->save();

        $this->syncRelations(
            $package,
            $data
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Package updated successfully.',
        ]);

        return redirect()->route(
            'admin.packages.index'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Destroy
    |--------------------------------------------------------------------------
    */

    public function destroy(
        Package $package
    ): RedirectResponse {

        /*
         * Delete ALL package files.
         */
        $this->files->deleteDirectory(
            UploadPath::package(
                $package->id
            )
        );

        $package->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Package deleted successfully.',
        ]);

        return redirect()->route(
            'admin.packages.index'
        );
    }

    /*
|--------------------------------------------------------------------------
| Show
|--------------------------------------------------------------------------
*/

    public function show(Package $package): JsonResponse
    {
        $package->load([
            'destinations',
            'inclusions',
            'activities',
            'reminders',
            'tourDates.times',
            'tourDates.pickupSchedules.pickupLocation',
        ]);

        return response()->json([
            'id' => $package->id,
            'package_name' => $package->package_name,
            'destination' => $package->destination,
            'destinations' => $package->destinations,
            'description' => $package->description,
            'price' => $package->price,
            'status' => $package->status,
            'operator_name' => $package->assignmentSummary()['operator_name'],
            'admin_name' => $package->assignmentSummary()['admin_name'],

            // Images come from the filesystem, NOT the database.
            'images' => $this->files->images(
                UploadPath::package($package->id)
            ),

            'inclusions' => $package->inclusions,
            'activities' => $package->activities,
            'reminders' => $package->reminders,
            'tour_dates' => $package->tourDates,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */

    private function syncRelations(
        Package $package,
        array $data
    ): void {
        /*
        |--------------------------------------------------------------------------
        | Destinations
        |--------------------------------------------------------------------------
        */

        $package->destinations()->delete();

        foreach ($data['destinations'] ?? [] as $position => $name) {
            $package->destinations()->create([
                'name' => $name,
                'position' => $position,
            ]);
        }

        $package->inclusions()->delete();

        foreach ($data['inclusions'] ?? [] as $inclusion) {
            if (! empty($inclusion['description'])) {
                $package->inclusions()->create([
                    'description' => $inclusion['description'],
                ]);
            }
        }

        $package->activities()->delete();

        foreach ($data['activities'] ?? [] as $activity) {
            if (! empty($activity['description'])) {
                $package->activities()->create([
                    'description' => $activity['description'],
                ]);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Reminders
        |--------------------------------------------------------------------------
        */

        $package->reminders()->delete();

        foreach ($data['reminders'] ?? [] as $reminder) {
            if (! empty($reminder['description'])) {
                $package->reminders()->create([
                    'description' => $reminder['description'],
                ]);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Tour Dates
        |--------------------------------------------------------------------------
        */

        $incomingDatesMap = collect($data['tour_dates'] ?? [])
            ->keyBy(fn ($td) => Carbon::parse($td['tour_date'])->format('Y-m-d'));

        $existingTourDates = $package->tourDates()
            ->with(['times', 'pickupSchedules' => fn ($q) => $q->withCount('bookings')])
            ->withCount('bookings')
            ->get();

        // 1. Delete removed tour dates (only those with 0 bookings)
        foreach ($existingTourDates as $existingDate) {
            $dateStr = $existingDate->tour_date->format('Y-m-d');
            if (! $incomingDatesMap->has($dateStr)) {
                if ($existingDate->bookings_count === 0) {
                    $existingDate->times()->delete();
                    $existingDate->pickupSchedules()->delete();
                    $existingDate->delete();
                }
            }
        }

        // 2. Create or Update each incoming tour date
        foreach ($data['tour_dates'] ?? [] as $tourDateData) {
            $dateStr = Carbon::parse($tourDateData['tour_date'])->format('Y-m-d');
            $capacity = (int) $tourDateData['capacity'];

            /** @var TourDate|null $tourDate */
            $tourDate = $existingTourDates->first(
                fn ($td) => $td->tour_date->format('Y-m-d') === $dateStr
            );

            if ($tourDate) {
                // Update capacity
                $tourDate->update(['capacity' => $capacity]);
            } else {
                // Create new tour date
                $tourDate = $package->tourDates()->create([
                    'tour_date' => $dateStr,
                    'capacity' => $capacity,
                ]);
            }

            // Sync Times
            $tourDate->times()->delete();
            foreach ($tourDateData['times'] ?? [] as $timeData) {
                if (! empty($timeData['time'])) {
                    $tourDate->times()->create([
                        'time' => $timeData['time'],
                        'description' => $timeData['description'] ?? '',
                    ]);
                }
            }

            // Sync Pickup Schedules
            $existingPickups = $tourDate->pickupSchedules()
                ->withCount('bookings')
                ->get();

            $incomingPickupLocationIds = collect($tourDateData['pickup_schedules'] ?? [])
                ->pluck('pickup_location_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            // Delete removed pickup schedules if 0 bookings
            foreach ($existingPickups as $existingPickup) {
                if (! in_array((int) $existingPickup->pickup_location_id, $incomingPickupLocationIds, true)) {
                    if ($existingPickup->bookings_count === 0) {
                        $existingPickup->delete();
                    }
                }
            }

            // Upsert incoming pickup schedules
            foreach ($tourDateData['pickup_schedules'] ?? [] as $pickupData) {
                $locationId = (int) $pickupData['pickup_location_id'];
                $pickupTime = $pickupData['pickup_time'];

                $existingPickup = $existingPickups->firstWhere('pickup_location_id', $locationId);

                if ($existingPickup) {
                    $existingPickup->update(['pickup_time' => $pickupTime]);
                } else {
                    $tourDate->pickupSchedules()->create([
                        'pickup_location_id' => $locationId,
                        'pickup_time' => $pickupTime,
                    ]);
                }
            }
        }
    }
}
