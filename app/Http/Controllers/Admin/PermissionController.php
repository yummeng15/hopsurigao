<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePermissionRequest;
use App\Http\Requests\Admin\UpdatePermissionRequest;
use App\Models\Permission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PermissionController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Index
    |--------------------------------------------------------------------------
    */

    public function index(): Response
    {
        return Inertia::render('admin/permissions/Index');
    }


    /*
    |--------------------------------------------------------------------------
    | DataTable
    |--------------------------------------------------------------------------
    */

    public function getData(Request $request): JsonResponse
    {
        $query = Permission::query();


        // Search
        $query->when($request->input('search'), function ($query, $search) {
            $query->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%");
                 $query->where('label', 'like', "%{$search}%");
                 $query->where('group', 'like', "%{$search}%");
            });
        });


        // Sorting
        $allowedSorts = [
            'id',
            'name',
            'label',
            'group',
            'description'
        ];

        $sort = $request->input('sort');

        if ($sort && in_array($sort, $allowedSorts, true)) {
            $order = $request->input('order', 'asc');

            if (! in_array($order, ['asc', 'desc'], true)) {
                $order = 'asc';
            }

            $query->orderBy($sort, $order);
        } else {
            $query->latest();
        }


        // Pagination
        $perPage = (int) $request->input('per_page', 10);

        $perPage = min(max($perPage, 1), 100);


        // Response
        return response()->json(
            $query
                ->paginate($perPage)
                ->through(function (Permission $permission) {
                    return [
                        'id' => $permission->id,
                        'name' => $permission->name,
                        'label' => $permission->label,
                        'group' => $permission->group,
                        'description' => $permission->description ?? null,
                    ];
                })
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Store
    |--------------------------------------------------------------------------
    */

    public function store(StorePermissionRequest $request): RedirectResponse
    {
        Permission::create(
            $request->validated()
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Permission created successfully.',
        ]);

        return redirect()->route('admin.permissions.index');
    }


    /*
    |--------------------------------------------------------------------------
    | Update
    |--------------------------------------------------------------------------
    */

    public function update(UpdatePermissionRequest $request, Permission $permission): RedirectResponse
    {

        $data = $request->validated();



        $permission->update($data);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Permission updated successfully.',
        ]);

        return redirect()->route('admin.permissions.index');
    }


    /*
    |--------------------------------------------------------------------------
    | Destroy
    |--------------------------------------------------------------------------
    */

    public function destroy(Permission $permission): RedirectResponse
    {


        $permission->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Permission deleted successfully.',
        ]);

        return redirect()->route('admin.permissions.index');
    }
}
