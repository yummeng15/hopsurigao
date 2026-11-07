<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\PickupLocation;
use App\Models\TourDate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class OperatorScheduleController extends Controller
{
    public function index(Request $request, int $package): Response
    {
        $operatorId = $this->operatorId($request);
        $record = $this->ownedPackage($operatorId, $package);

        $dates = $record->tourDates()
            ->with(['times', 'pickupSchedules.pickupLocation'])
            ->withCount('bookings')
            ->withSum([
                'bookings as reserved_guests' => fn ($query) => $query
                    ->whereIn('booking_status', ['pending', 'confirmed']),
            ], 'number_of_guests')
            ->orderBy('tour_date')
            ->get()
            ->map(fn (TourDate $date) => [
                'id' => (int) $date->id,
                'tour_date' => $date->tour_date->format('Y-m-d'),
                'capacity' => (int) $date->capacity,
                'reserved_guests' => (int) ($date->reserved_guests ?? 0),
                'has_bookings' => (int) $date->bookings_count > 0,
                'travel_closed' => (bool) $date->travel_closed,
                'times' => $date->times->map(fn ($time) => [
                    'time' => substr((string) $time->getRawOriginal('time'), 0, 5),
                    'description' => (string) $time->description,
                ])->values()->all(),
                'pickup_schedules' => $date->pickupSchedules->map(fn ($pickup) => [
                    'pickup_location_id' => (int) $pickup->pickup_location_id,
                    'pickup_location_name' => $pickup->pickupLocation?->name ?? 'Unknown location',
                    'pickup_time' => substr((string) $pickup->getRawOriginal('pickup_time'), 0, 5),
                ])->values()->all(),
            ])->values()->all();

        $locations = PickupLocation::query()
            ->where('status', 'active')
            ->where(fn ($query) => $query
                ->whereNull('tour_operator_id')
                ->orWhere('tour_operator_id', $operatorId))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($location) => [
                'id' => (int) $location->id,
                'name' => $location->name,
            ])->all();

        return Inertia::render('operator/Schedules', [
            'package' => [
                'id' => (int) $record->id,
                'name' => $record->package_name,
                'status' => $record->status,
            ],
            'dates' => $dates,
            'locations' => $locations,
            'today' => today()->format('Y-m-d'),
        ]);
    }

    public function store(Request $request, int $package): RedirectResponse
    {
        $operatorId = $this->operatorId($request);
        $record = $this->ownedPackage($operatorId, $package);
        $data = $this->validateSchedule($request, $operatorId, true);

        DB::transaction(function () use ($record, $data, $operatorId): void {
            if ($record->tourDates()->whereDate('tour_date', $data['tour_date'])->exists()) {
                throw ValidationException::withMessages([
                    'tour_date' => 'A schedule already exists for this package and date.',
                ]);
            }

            $date = $record->tourDates()->create([
                'tour_date' => $data['tour_date'],
                'capacity' => $data['capacity'],
                'travel_closed' => false,
            ]);

            $this->replaceDetails($date, $data, $operatorId);
        });

        return redirect()->route('operator.schedules.index', $record->id)
            ->with('success', 'Tour date and pickup schedules created.');
    }

    public function update(Request $request, int $package, int $tourDate): RedirectResponse
    {
        $operatorId = $this->operatorId($request);
        $record = $this->ownedPackage($operatorId, $package);

        DB::transaction(function () use ($request, $record, $tourDate, $operatorId): void {
            $date = $record->tourDates()
                ->whereKey($tourDate)
                ->lockForUpdate()
                ->firstOrFail();

            if ($date->tour_date->isBefore(today())) {
                throw ValidationException::withMessages([
                    'capacity' => 'Past tour dates cannot be changed.',
                ]);
            }

            $hasBookings = $date->bookings()->exists();

            // Protect existing bookings from schedule changes.
            if ($hasBookings && ($request->exists('times') || $request->exists('pickup_schedules'))) {
                throw ValidationException::withMessages([
                    'capacity' => 'Tour times and pickups cannot change after a booking exists. Update capacity only.',
                ]);
            }

            $data = $hasBookings
                ? $request->validate([
                    'capacity' => ['required', 'integer', 'min:1', 'max:10000'],
                ])
                : $this->validateSchedule($request, $operatorId, false);

            $reserved = (int) $date->bookings()
                ->whereIn('booking_status', ['pending', 'confirmed'])
                ->sum('number_of_guests');

            if ((int) $data['capacity'] < $reserved) {
                throw ValidationException::withMessages([
                    'capacity' => "Capacity cannot be below {$reserved} reserved guests.",
                ]);
            }

            $date->update([
                'capacity' => $data['capacity'],
            ]);

            if (! $hasBookings) {
                $this->replaceDetails($date, $data, $operatorId);
            }
        });

        return redirect()->route('operator.schedules.index', $record->id)
            ->with('success', 'Tour schedule updated.');
    }

    private function operatorId(Request $request): int
    {
        $user = $request->user();

        abort_unless($user?->role?->name === 'Admin', 403);
        abort_unless($user->tourOperator?->is_active === true, 403);

        return (int) $user->tour_operator_id;
    }

    private function ownedPackage(int $operatorId, int $id): Package
    {
        return Package::query()
            ->where('tour_operator_id', $operatorId)
            ->findOrFail($id);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateSchedule(Request $request, int $operatorId, bool $creating): array
    {
        $rules = [
            'capacity' => [
                'required',
                'integer',
                'min:1',
                'max:10000',
            ],
            'times' => [
                'required',
                'array',
                'min:1',
                'max:8',
            ],
            'times.*.time' => [
                'required',
                'date_format:H:i',
                'distinct',
            ],
            'times.*.description' => [
                'required',
                'string',
                'max:255',
            ],
            'pickup_schedules' => [
                'required',
                'array',
                'min:1',
                'max:15',
            ],

            // Accept locations typed directly by the operator.
            'pickup_schedules.*.pickup_location_name' => [
                'required_without:pickup_schedules.*.pickup_location_id',
                'nullable',
                'string',
                'max:255',
            ],

            // Preserve support for existing location IDs.
            'pickup_schedules.*.pickup_location_id' => [
                'required_without:pickup_schedules.*.pickup_location_name',
                'nullable',
                'integer',
                'distinct',
                Rule::exists('pickup_locations', 'id')
                    ->where(function ($query) use ($operatorId): void {
                        $query->where('status', 'active')
                            ->where(function ($scope) use ($operatorId): void {
                                $scope->whereNull('tour_operator_id')
                                    ->orWhere('tour_operator_id', $operatorId);
                            });
                    }),
            ],
            'pickup_schedules.*.pickup_time' => [
                'required',
                'date_format:H:i',
            ],
        ];

        if ($creating) {
            $rules['tour_date'] = [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:today',
            ];
        } else {
            $rules['tour_date'] = ['prohibited'];
        }

        return $request->validate($rules);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function replaceDetails(TourDate $date, array $data, int $operatorId): void
    {
        // Update departure times.
        $date->times()->delete();

        foreach ($data['times'] as $time) {
            $date->times()->create([
                'time' => $time['time'],
                'description' => $time['description'],
            ]);
        }

        // Update pickup schedules.
        $date->pickupSchedules()->delete();

        foreach ($data['pickup_schedules'] as $pickup) {
            $name = trim((string) ($pickup['pickup_location_name'] ?? ''));

            if ($name !== '') {
                // Look for an existing active location.
                $location = PickupLocation::query()
                    ->where('status', 'active')
                    ->where(function ($query) use ($operatorId): void {
                        $query->where('tour_operator_id', $operatorId)
                            ->orWhereNull('tour_operator_id');
                    })
                    ->whereRaw('LOWER(name) = LOWER(?)', [$name])
                    ->orderByRaw(
                        'CASE WHEN tour_operator_id = ? THEN 0 ELSE 1 END',
                        [$operatorId]
                    )
                    ->first();

                if (! $location) {
                    // The PickupLocation model automatically
                    // assigns the logged-in tour operator.
                    $location = PickupLocation::create([
                        'name' => $name,
                        'status' => 'active',
                    ]);
                }

                $locationId = (int) $location->id;
            } else {
                // Existing ID-based pickup.
                $locationId = (int) $pickup['pickup_location_id'];
            }

            $date->pickupSchedules()->create([
                'pickup_location_id' => $locationId,
                'pickup_time' => $pickup['pickup_time'],
            ]);
        }
    }
}
