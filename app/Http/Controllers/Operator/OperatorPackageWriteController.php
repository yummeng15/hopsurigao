<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\PackageDestination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Manage basic tour package information.
 *
 * New packages are saved as inactive internally.
 * The UI displays this status as "Draft".
 *
 * A package can be published only after
 * its required tour schedules are configured.
 */
class OperatorPackageWriteController extends Controller
{
    public function create(Request $request): Response
    {
        $this->operatorId($request);

        return Inertia::render('operator/PackageForm', [
            'mode' => 'create',
            'package' => null,
            'hasActiveBookings' => false,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $operatorId = $this->operatorId($request);

        [$data, $destinations] = $this->validatedData($request);

        DB::transaction(function () use (
            $operatorId,
            $data,
            $destinations
        ): void {
            $package = new Package();

            $package->fill([
                'package_name' => $data['package_name'],
                'boat_name' => $data['boat_name'],
                'destination' => implode(', ', $destinations),
                'description' => $data['description'] ?? null,
                'price' => $data['price'],

                // Draft status until properly published.
                'status' => 'inactive',
            ]);

            // Ownership is controlled by the backend.
            $package->tour_operator_id = $operatorId;
            $package->save();

            foreach ($destinations as $position => $name) {
                $package->destinations()->create([
                    'name' => $name,
                    'position' => $position,
                ]);
            }
        });

        return redirect()
            ->route('operator.packages.index')
            ->with(
                'success',
                'Package created as a draft. Add schedules, then publish it when ready.'
            );
    }

    public function edit(
        Request $request,
        int $package
    ): Response {
        $operatorId = $this->operatorId($request);

        $record = $this->ownedPackage(
            $operatorId,
            $package
        );

        $record->load('destinations');

        $destinations = $record->destinations
            ->pluck('name')
            ->all();

        if ($destinations === []) {
            $destinations = $this->destinationNames(
                (string) $record->destination
            );
        }

        return Inertia::render('operator/PackageForm', [
            'mode' => 'edit',

            'package' => [
                'id' => $record->id,
                'package_name' => $record->package_name,
                'boat_name' => $record->boat_name,
                'destinations_text' => implode(
                    "\n",
                    $destinations
                ),
                'description' => $record->description,
                'price' => (string) $record->price,
                'status' => $record->status,
            ],

            'hasActiveBookings' => $this->hasActiveBookings(
                $record
            ),
        ]);
    }

    public function update(
        Request $request,
        int $package
    ): RedirectResponse {
        $operatorId = $this->operatorId($request);

        $record = $this->ownedPackage(
            $operatorId,
            $package
        );

        [$data, $destinations] = $this->validatedData(
            $request
        );

        $oldDestinations = $record->destinations()
            ->orderBy('position')
            ->pluck('name')
            ->all();

        if ($oldDestinations === []) {
            $oldDestinations = $this->destinationNames(
                (string) $record->destination
            );
        }

        // Prevent changing booked tour essentials
        // while reservations are active.
        if ($this->hasActiveBookings($record)) {
            $errors = [];

            if (
                trim((string) $record->boat_name)
                !== trim($data['boat_name'])
            ) {
                $errors['boat_name'] =
                    'The boat cannot be changed while active bookings exist.';
            }

            if ($oldDestinations !== $destinations) {
                $errors['destinations_text'] =
                    'Destinations cannot be changed while active bookings exist.';
            }

            if (
                (int) round((float) $record->price * 100)
                !== (int) round((float) $data['price'] * 100)
            ) {
                $errors['price'] =
                    'The price cannot be changed while active bookings exist.';
            }

            if ($errors !== []) {
                throw ValidationException::withMessages(
                    $errors
                );
            }
        }

        DB::transaction(function () use (
            $record,
            $data,
            $destinations,
            $oldDestinations
        ): void {
            $record->fill([
                'package_name' => $data['package_name'],
                'boat_name' => $data['boat_name'],
                'destination' => implode(
                    ', ',
                    $destinations
                ),
                'description' => $data['description'] ?? null,
                'price' => $data['price'],
            ]);

            // Preserve existing publication status
            // and operator ownership.
            $record->save();

            if ($oldDestinations !== $destinations) {
                $record->destinations()->delete();

                foreach ($destinations as $position => $name) {
                    $record->destinations()->create([
                        'name' => $name,
                        'position' => $position,
                    ]);
                }
            }
        });

        return redirect()
            ->route('operator.packages.index')
            ->with(
                'success',
                'Package details updated. Existing schedules and images were preserved.'
            );
    }

    private function operatorId(Request $request): int
    {
        $user = $request->user();

        abort_unless(
            $user?->role?->name === 'Admin',
            403
        );

        abort_unless(
            $user->tourOperator?->is_active === true,
            403
        );

        return (int) $user->tour_operator_id;
    }

    private function ownedPackage(
        int $operatorId,
        int $packageId
    ): Package {
        return Package::query()
            ->where('tour_operator_id', $operatorId)
            ->findOrFail($packageId);
    }

    /**
     * @return array{0: array<string, mixed>, 1: list<string>}
     */
    private function validatedData(Request $request): array
    {
        $data = $request->validate([
            'package_name' => [
                'required',
                'string',
                'max:255',
            ],
            'boat_name' => [
                'required',
                'string',
                'max:255',
            ],
            'destinations_text' => [
                'required',
                'string',
                'max:2000',
            ],
            'description' => [
                'nullable',
                'string',
                'max:10000',
            ],
            'price' => [
                'required',
                'numeric',
                'min:0',
                'max:99999999.99',
            ],
        ]);

        $destinations = $this->destinationNames(
            $data['destinations_text']
        );

        if (
            $destinations === [] ||
            count($destinations) > 12
        ) {
            throw ValidationException::withMessages([
                'destinations_text' =>
                    'Enter between 1 and 12 destinations, one per line.',
            ]);
        }

        foreach ($destinations as $name) {
            if (mb_strlen($name) > 255) {
                throw ValidationException::withMessages([
                    'destinations_text' =>
                        'Each destination must be 255 characters or fewer.',
                ]);
            }
        }

        if (
            mb_strlen(implode(', ', $destinations)) > 255
        ) {
            throw ValidationException::withMessages([
                'destinations_text' =>
                    'The combined destination names must be 255 characters or fewer.',
            ]);
        }

        return [$data, $destinations];
    }

    /**
     * @return list<string>
     */
    private function destinationNames(string $text): array
    {
        $names = preg_split('/[\r\n,]+/u', $text) ?: [];

        return PackageDestination::normalizeNames(
            $names
        );
    }

    private function hasActiveBookings(
        Package $package
    ): bool {
        return $package->tourDates()
            ->whereHas(
                'bookings',
                fn ($query) => $query->whereIn(
                    'booking_status',
                    ['pending', 'confirmed']
                )
            )
            ->exists();
    }
}
