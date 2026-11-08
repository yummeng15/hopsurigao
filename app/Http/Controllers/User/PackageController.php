<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Services\FileUploadService;
use App\Services\TourDateAvailabilityService;
use App\Services\TourDateBookingAvailability;
use Inertia\Inertia;
use Inertia\Response;

class PackageController extends Controller
{
    public function __construct(
        private FileUploadService $files,
        private TourDateBookingAvailability $bookingAvailability,
        private TourDateAvailabilityService $tourDates,
    ) {}

    public function index(): Response
    {
        $packages = Package::query()
            ->where('status', 'active')
            ->withCount('tourDates')
            ->with([
                'tourDates' => function ($query) {
                    $query->whereDate(
                        'tour_date',
                        '>=',
                        now()->toDateString()
                    )->with('weatherForecast');
                },
            ])
            ->latest()
            ->get()
            ->map(function (Package $package) {
                $nextDate = $package->tourDates->sortBy('tour_date')->first();
                $route = $package->routeSummary();

                return [
                    'id' => $package->id,
                    'package_name' => $package->package_name,
                    'destination' => $package->destination,
                    'stops' => $route['stops'],
                    'island_count' => $route['island_count'],
                    'description' => $package->description,
                    'price' => $package->price,
                    'status' => $package->status,

                    'tour_dates_count' => $package->tourDates->count(),

                    'next_tour_date' => $nextDate ? [
                        'tour_date' => $nextDate->tour_date->toDateString(),
                        'weather_forecast' => $nextDate->weatherForecast,
                    ] : null,

                    'images' => $this->files->packageImages(
                        $package
                    ),
                ];
            });

        return Inertia::render(
            'user/packages/Index',
            [
                'packages' => $packages,
            ]
        );
    }

    /**
     * Full package page.
     */
    public function show(Package $package): Response
    {
        abort_unless(
            $package->status === 'active',
            404
        );

        $this->tourDates->ensure($package);

        $package->load([
            'inclusions',
            'activities',
            'reminders',

            'tourDates' => function ($query) {
                $query
                    ->whereDate(
                        'tour_date',
                        '>=',
                        now()->toDateString()
                    )
                    ->orderBy('tour_date')
                    ->with([
                        'times',
                        'weatherForecast',
                        'pickupSchedules.pickupLocation',
                        'bookings' => function ($bookingQuery) {
                            $bookingQuery->whereIn(
                                'booking_status',
                                [
                                    'pending',
                                    'confirmed',
                                ]
                            );
                        },
                    ]);
            },
        ]);

        $route = $package->routeSummary();
        $assignment = $package->assignmentSummary();

        $tourDates = $package->tourDates
            ->map(function ($tourDate) use ($package) {

                /*
             * Only pending and confirmed bookings
             * consume tour-date capacity.
             *
             * Cancelled bookings do NOT consume capacity.
             */
                $bookedGuests = (int) $tourDate->bookings
                    ->sum('number_of_guests');

                $forecast = $tourDate->weatherForecast;
                $hasCompleteSchedule = $tourDate->times->isNotEmpty()
                    && $tourDate->pickupSchedules->isNotEmpty();

                $availability = $this->bookingAvailability->assess(
                    $package,
                    $tourDate,
                    $bookedGuests,
                    $forecast?->advisory_level,
                    $forecast !== null,
                    $hasCompleteSchedule,
                );

                return [
                    'id' => $tourDate->id,

                    'package_id' => $tourDate->package_id,

                    'tour_date' => $tourDate->tour_date->toDateString(),

                    'capacity' => $tourDate->capacity,

                    'booked_guests' => $bookedGuests,

                    'remaining_capacity' => $availability['remaining_capacity'],

                    'is_full' => $availability['is_full'],

                    'availability_status' => $availability['status'],

                    'availability_label' => $availability['label'],

                    'availability_reason' => $availability['reason'],

                    'forecast_notice' => $availability['forecast_notice'],

                    'booking_closed' => $availability['booking_closed'],

                    'booking_closed_reason' => $availability['booking_closed_reason'],

                    'bookable' => $availability['bookable'],

                    'has_complete_schedule' => $hasCompleteSchedule,

                    'weather_forecast' => $forecast,

                    'times' => $tourDate->times,

                    'pickup_schedules' => $tourDate->pickupSchedules,
                ];
            })
            ->values();

        return Inertia::render(
            'user/packages/Show',
            [
                'package' => [
                    'id' => $package->id,

                    'package_name' => $package->package_name,

                    'boat_name' => $package->boat_name,

                    'destination' => $package->destination,

                    'stops' => $route['stops'],

                    'island_count' => $route['island_count'],

                    'description' => $package->description,

                    'price' => $package->price,

                    'status' => $package->status,

                    'operator_name' => $assignment['operator_name'],

                    'admin_name' => $assignment['admin_name'],

                    'inclusions' => $package->inclusions,

                    'activities' => $package->activities,

                    'reminders' => $package->reminders,

                    'tour_dates' => $tourDates,
                ],

                'images' => $this->files->packageImages(
                    $package
                ),
            ]
        );
    }

    /**
     * Images-only endpoint used by PackageImageDialog.
     */
    public function images(Package $package)
    {
        abort_unless(
            $package->status === 'active',
            404
        );

        return response()->json([
            'id' => $package->id,
            'package_name' => $package->package_name,

            'images' => $this->files->packageImages(
                $package
            ),
        ]);
    }
}
