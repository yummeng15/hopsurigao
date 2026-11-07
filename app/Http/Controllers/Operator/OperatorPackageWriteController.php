<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\PackageDestination;
use App\Services\FileUploadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\File;
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
    public function __construct(
        private FileUploadService $files,
    ) {}

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

        [$data, $destinations, $photo] = $this->validatedData($request);

        $packageId = null;

        DB::transaction(function () use (
            $operatorId,
            $data,
            $destinations,
            $photo,
            &$packageId
        ): void {
            $package = new Package;

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

            if ($photo instanceof UploadedFile) {
                $path = $this->files->storePackageCover(
                    $package,
                    $photo
                );

                $package->forceFill([
                    'cover_photo' => $path,
                ])->save();
            }

            $packageId = (int) $package->id;
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

        $coverImages = $this->files->packageImages($record);
        $cover = $coverImages[0] ?? null;

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
                'cover_photo_url' => $cover['url'] ?? null,
                'cover_photo_path' => $cover['path'] ?? null,
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

        [$data, $destinations, $photo] = $this->validatedData(
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
            $oldDestinations,
            $photo
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

            if ($photo instanceof UploadedFile) {
                $path = $this->files->storePackageCover(
                    $record,
                    $photo,
                    $record->cover_photo
                );

                $record->forceFill([
                    'cover_photo' => $path,
                ])->save();
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
     * @return array{0: array<string, mixed>, 1: list<string>, 2: UploadedFile|null}
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
            'cover_photo' => [
                'nullable',
                File::image()
                    ->types(['jpg', 'jpeg', 'png', 'webp'])
                    ->max(5 * 1024),
            ],
        ], [
            'cover_photo.image' => 'The package photo must be a valid image.',
            'cover_photo.mimes' => 'The package photo must be a JPG, JPEG, PNG, or WebP file.',
            'cover_photo.max' => 'The package photo may not be greater than 5 MB.',
        ]);

        $destinations = $this->destinationNames(
            $data['destinations_text']
        );

        if (
            $destinations === [] ||
            count($destinations) > 12
        ) {
            throw ValidationException::withMessages([
                'destinations_text' => 'Enter between 1 and 12 destinations, one per line.',
            ]);
        }

        foreach ($destinations as $name) {
            if (mb_strlen($name) > 255) {
                throw ValidationException::withMessages([
                    'destinations_text' => 'Each destination must be 255 characters or fewer.',
                ]);
            }
        }

        if (
            mb_strlen(implode(', ', $destinations)) > 255
        ) {
            throw ValidationException::withMessages([
                'destinations_text' => 'The combined destination names must be 255 characters or fewer.',
            ]);
        }

        $photo = $request->file('cover_photo');

        return [
            $data,
            $destinations,
            $photo instanceof UploadedFile ? $photo : null,
        ];
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
