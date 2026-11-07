<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePickupLocationRequest;
use App\Http\Requests\Admin\UpdatePickupLocationRequest;
use App\Models\PickupLocation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PickupLocationController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Index
    |--------------------------------------------------------------------------
    */

    public function index(): Response
    {
        return Inertia::render(
            'admin/pickup-locations/Index'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | DataTable
    |--------------------------------------------------------------------------
    */

    public function getData(Request $request): JsonResponse
    {
        $query = PickupLocation::query()
            ->with('tourOperator:id,name');

        if ($search = $request->input('search')) {
            $query->where(function ($query) use ($search) {
                $query
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%");
            });
        }

        $allowedSorts = [
            'id',
            'name',
            'status',
            'created_at',
        ];

        $sort = $request->input('sort');
        $order = $request->input('order', 'asc');

        if (
            $sort &&
            in_array($sort, $allowedSorts, true)
        ) {
            $query->orderBy(
                $sort,
                $order === 'desc' ? 'desc' : 'asc'
            );
        } else {
            $query->latest();
        }

        $perPage = min(
            max((int) $request->input('per_page', 10), 1),
            100
        );

        $user = $request->user();

        $isSuperadmin = $user?->role?->name === 'Superadmin';

        $operatorId = $user?->tour_operator_id;

        return response()->json(
            $query
                ->paginate($perPage)
                ->through(
                    function (PickupLocation $location) use (
                        $isSuperadmin,
                        $operatorId
                    ) {
                        $isShared = $location->tour_operator_id === null;

                        $isMine = ! $isShared
                            && $operatorId !== null
                            && (int) $location->tour_operator_id === (int) $operatorId;

                        return [
                            'id' => $location->id,

                            'name' => $location->name,

                            'address' => $location->address,

                            'latitude' => $location->latitude,

                            'longitude' => $location->longitude,

                            'description' => $location->description,

                            'status' => $location->status,

                            'is_shared' => $isShared,

                            'scope_label' => $isShared
                                ? 'Shared'
                                : ($isMine
                                    ? 'My Operator'
                                    : ($location->tourOperator?->name ?? 'Operator')),

                            'can_manage' => $isSuperadmin || $isMine,
                        ];
                    }
                )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Store
    |--------------------------------------------------------------------------
    */

    public function store(
        StorePickupLocationRequest $request
    ): RedirectResponse {
        /*
         * The PickupLocation model automatically:
         *
         * - Assigns the current operator to an Admin-created location.
         * - Leaves a Superadmin-created location shared.
         */

        PickupLocation::create($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Pickup location created successfully.',
        ]);

        return redirect()->route(
            'admin.pickup-locations.index'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Update
    |--------------------------------------------------------------------------
    */

    public function update(
        UpdatePickupLocationRequest $request,
        PickupLocation $pickupLocation
    ): RedirectResponse {
        $this->ensureCanManage($request, $pickupLocation);

        $pickupLocation->update($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Pickup location updated successfully.',
        ]);

        return redirect()->route(
            'admin.pickup-locations.index'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Destroy
    |--------------------------------------------------------------------------
    */

    public function destroy(
        Request $request,
        PickupLocation $pickupLocation
    ): RedirectResponse {
        $this->ensureCanManage($request, $pickupLocation);

        /*
         * Check every related schedule, including schedules belonging
         * to other operators, before allowing deletion.
         */

        if (
            $pickupLocation
                ->pickupSchedules()
                ->withoutGlobalScopes()
                ->exists()
        ) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' =>
                    'This location is used in existing tour schedules '
                    .'and cannot be deleted.',
            ]);

            return redirect()->route(
                'admin.pickup-locations.index'
            );
        }

        $pickupLocation->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Pickup location deleted successfully.',
        ]);

        return redirect()->route(
            'admin.pickup-locations.index'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    */

    private function ensureCanManage(
        Request $request,
        PickupLocation $location
    ): void {
        $user = $request->user();

        if ($user?->role?->name === 'Superadmin') {
            return;
        }

        $operator = $user?->tourOperator;

        abort_unless(
            $user?->role?->name === 'Admin'
                && $operator !== null
                && $operator->is_active
                && $location->tour_operator_id !== null
                && (int) $location->tour_operator_id === (int) $operator->id,
            403,
            'You cannot manage this pickup location.'
        );
    }
}