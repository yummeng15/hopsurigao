<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | INDEX
    |--------------------------------------------------------------------------
    */

    public function index(): Response
    {
        return Inertia::render('admin/users/Index');
    }

    /*
    |--------------------------------------------------------------------------
    | DATATABLE
    |--------------------------------------------------------------------------
    */

    public function getData(Request $request): JsonResponse
    {
        $query = User::query()
            ->with('role');

        /*
        |--------------------------------------------------------------------------
        | SEARCH
        |--------------------------------------------------------------------------
        */

        if ($search = $request->input('search')) {
            $query->where(function ($query) use ($search) {
                $query
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        /*
        |--------------------------------------------------------------------------
        | SORTING
        |--------------------------------------------------------------------------
        */

        $allowedSorts = [
            'id',
            'name',
            'email',
            'created_at',
        ];

        $sort = $request->input('sort');

        if (
            $sort &&
            in_array($sort, $allowedSorts, true)
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

        /*
        |--------------------------------------------------------------------------
        | PAGINATION
        |--------------------------------------------------------------------------
        */

        $perPage = (int) $request->input(
            'per_page',
            10
        );

        $perPage = min(
            max($perPage, 1),
            100
        );

        /*
        |--------------------------------------------------------------------------
        | RESPONSE
        |--------------------------------------------------------------------------
        */

        return response()->json(
            $query
                ->paginate($perPage)
                ->through(function (User $user) {
                    return [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'role_name' => $user->role?->name ?? 'No Role',
                    ];
                })
        );
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE
    |--------------------------------------------------------------------------
    */

    public function create(): never
    {
        abort(
            403,
            'Users can only be viewed.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | STORE
    |--------------------------------------------------------------------------
    */

    public function store(
        StoreUserRequest $request
    ): never {
        abort(
            403,
            'Users can only be viewed.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | EDIT
    |--------------------------------------------------------------------------
    */

    public function edit(User $user): never
    {
        abort(
            403,
            'Users can only be viewed.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE
    |--------------------------------------------------------------------------
    */

    public function update(
        UpdateUserRequest $request,
        User $user
    ): never {
        abort(
            403,
            'Users can only be viewed.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | DESTROY
    |--------------------------------------------------------------------------
    */

    public function destroy(User $user): never
    {
        abort(
            403,
            'Users can only be viewed.'
        );
    }
}
