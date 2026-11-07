<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\TourDate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OperatorPackagePublicationController extends Controller
{
    public function publish(Request $request, int $package): RedirectResponse
    {
        $operatorId = $this->operatorId($request);

        DB::transaction(function () use ($operatorId, $package): void {
            $record = $this->ownedPackageForUpdate($operatorId, $package);

            if ($record->status === 'active') {
                return;
            }

            $issues = $this->publicationIssues($record, $operatorId);

            if ($issues !== []) {
                throw ValidationException::withMessages([
                    'publication' => implode(' ', $issues),
                ]);
            }

            $record->status = 'active';
            $record->save();
        });

        return redirect()->route('operator.packages.index')
            ->with('success', 'Your tour package is now published.');
    }

    public function unpublish(Request $request, int $package): RedirectResponse
    {
        $operatorId = $this->operatorId($request);

        DB::transaction(function () use ($operatorId, $package): void {
            $record = $this->ownedPackageForUpdate($operatorId, $package);

            if ($record->status === 'inactive') {
                return;
            }

            // Existing reservations remain intact; only NEW bookings are blocked.
            $record->status = 'inactive';
            $record->save();
        });

        return redirect()->route('operator.packages.index')
            ->with('success', 'Package unpublished. Existing reservations still need to be honored or handled.');
    }

    private function operatorId(Request $request): int
    {
        $user = $request->user();

        abort_unless($user?->role?->name === 'Admin', 403);
        abort_unless($user->tourOperator?->is_active === true, 403);

        return (int) $user->tour_operator_id;
    }

    private function ownedPackageForUpdate(int $operatorId, int $packageId): Package
    {
        // Route IDs are untrusted. Always resolve ownership from the session.
        return Package::query()
            ->where('tour_operator_id', $operatorId)
            ->whereKey($packageId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    /** @return list<string> */
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

        // At least one future, open tour date with a departure time and an
        // active, authorized pickup point must exist before publishing.
        $readyDateExists = TourDate::query()
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
}
