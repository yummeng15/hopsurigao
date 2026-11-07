<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Services\FileUploadService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OperatorPackagesController extends Controller
{
    public function __construct(
        private FileUploadService $files,
    ) {}

    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        // Keep this authorization even though routes/operator.php is guarded.
        abort_unless($user?->role?->name === 'Admin', 403);

        $operator = $user->tourOperator;
        abort_unless($operator?->canManageRecords() === true, 403,
            'An approved tour operator assignment is required.');

        // Never rely on the sidebar or user-provided operator IDs to filter
        // tenant-owned data. The authenticated account is the source of truth.
        $packages = Package::query()
            ->where('tour_operator_id', $operator->id)
            ->withCount('tourDates')
            ->orderByDesc('id')
            ->paginate(10)
            ->through(function (Package $package): array {
                $images = $this->files->packageImages($package);

                return [
                    'id' => (int) $package->id,
                    'package_name' => (string) $package->package_name,
                    'destination' => (string) $package->destination,
                    'boat_name' => $package->boat_name,
                    'price' => (string) $package->price,
                    'status' => (string) $package->status,
                    'under_maintenance' => (bool) $package->under_maintenance,
                    'tour_dates_count' => (int) $package->tour_dates_count,
                    'cover_photo_url' => $images[0]['url'] ?? null,
                ];
            });

        return Inertia::render('operator/Packages', [
            'operatorName' => $operator->name,
            'packages' => $packages,
        ]);
    }
}
