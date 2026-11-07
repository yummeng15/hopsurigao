<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRoleRequest;
use App\Http\Requests\Admin\UpdateRoleRequest;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Protected System Roles
    |--------------------------------------------------------------------------
    */

    private const PROTECTED_ROLES = [
        'Superadmin',
        'Admin',
        'User',
    ];

    /*
    |--------------------------------------------------------------------------
    | Index
    |--------------------------------------------------------------------------
    */

    public function index(): Response
    {
        return Inertia::render('admin/roles/Index');
    }

    /*
    |--------------------------------------------------------------------------
    | DataTable
    |--------------------------------------------------------------------------
    */

    public function getData(Request $request): JsonResponse
    {
        $query = Role::query()
            ->withCount('users');

        if ($search = $request->input('search')) {
            $query->where(
                'name',
                'like',
                "%{$search}%"
            );
        }

        $allowedSorts = [
            'id',
            'name',
            'description',
        ];

        $sort = $request->input('sort');
        $order = $request->input('order', 'asc');

        if ($sort && in_array($sort, $allowedSorts, true)) {
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

        return response()->json(
            $query
                ->paginate($perPage)
                ->through(function (Role $role) {
                    $isProtected = in_array(
                        $role->name,
                        self::PROTECTED_ROLES,
                        true
                    );

                    $usersCount = (int) $role->users_count;

                    return [
                        'id' => $role->id,
                        'name' => $role->name,
                        'description' => $role->description,
                        'users_count' => $usersCount,
                        'is_protected' => $isProtected,
                        'can_edit' => ! $isProtected,
                        'can_delete' => ! $isProtected
                            && $usersCount === 0,
                    ];
                })
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Create Role
    |--------------------------------------------------------------------------
    */

    public function store(
        StoreRoleRequest $request
    ): RedirectResponse {
        Role::create($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Role created successfully.',
        ]);

        return redirect()->route('admin.roles.index');
    }

    /*
    |--------------------------------------------------------------------------
    | Update Role
    |--------------------------------------------------------------------------
    */

    public function update(
        UpdateRoleRequest $request,
        Role $role
    ): RedirectResponse {
        $this->ensureRoleIsNotProtected($role);

        $role->update($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Role updated successfully.',
        ]);

        return redirect()->route('admin.roles.index');
    }

    /*
    |--------------------------------------------------------------------------
    | Delete Role
    |--------------------------------------------------------------------------
    */

    public function destroy(Role $role): RedirectResponse
    {
        $this->ensureRoleIsNotProtected($role);

        if ($role->users()->exists()) {
            return back()->withErrors([
                'role' =>
                    'This role is assigned to existing users '
                    .'and cannot be deleted.',
            ]);
        }

        // Remove permission assignments for this custom role.
        $role->permissions()->detach();

        $role->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Role deleted successfully.',
        ]);

        return redirect()->route('admin.roles.index');
    }

    /*
    |--------------------------------------------------------------------------
    | Core Role Protection
    |--------------------------------------------------------------------------
    */

    private function ensureRoleIsNotProtected(Role $role): void
    {
        abort_if(
            in_array(
                $role->name,
                self::PROTECTED_ROLES,
                true
            ),
            403,
            'This is a protected system role and cannot be modified.'
        );
    }
}