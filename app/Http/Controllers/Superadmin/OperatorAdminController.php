<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\TourOperator;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class OperatorAdminController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorizeSuperadmin($request);

        $adminRole = Role::query()
            ->where('name', 'Admin')
            ->firstOrFail();

        $operators = TourOperator::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $admins = User::query()
            ->where('role_id', $adminRole->id)
            ->with('tourOperator:id,name,is_active')
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'email',
                'tour_operator_id',
                'created_at',
            ])
            ->map(fn (User $admin): array => [
                'id' => (int) $admin->id,
                'name' => $admin->name,
                'email' => $admin->email,
                'operator_name' =>
                    $admin->tourOperator?->name ?? 'Unassigned',
                'operator_active' =>
                    $admin->tourOperator?->is_active ?? false,
                'is_assigned' =>
                    $admin->tour_operator_id !== null,
                'created_at' =>
                    $admin->created_at?->format('M d, Y'),
            ])
            ->values()
            ->all();

        $unassignedAdmins = User::query()
            ->where('role_id', $adminRole->id)
            ->whereNull('tour_operator_id')
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return Inertia::render(
            'superadmin/operator-admins/Index',
            [
                'operators' => $operators,
                'admins' => $admins,
                'unassignedAdmins' => $unassignedAdmins,
            ]
        );
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeSuperadmin($request);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email'),
            ],
            'password' => [
                'required',
                'confirmed',
                Password::min(8)
                    ->mixedCase()
                    ->numbers(),
            ],
            'tour_operator_id' => [
                'required',
                'integer',
                Rule::exists('tour_operators', 'id')
                    ->where('is_active', true),
            ],
        ]);

        DB::transaction(function () use ($validated): void {
            $operator = TourOperator::query()
                ->whereKey($validated['tour_operator_id'])
                ->where('is_active', true)
                ->lockForUpdate()
                ->firstOrFail();

            $adminRole = Role::query()
                ->where('name', 'Admin')
                ->firstOrFail();

            $admin = new User();

            $admin->name = $validated['name'];
            $admin->email = $validated['email'];

            // User model's hashed cast secures the password.
            $admin->password = $validated['password'];

            $admin->role_id = $adminRole->id;
            $admin->tour_operator_id = $operator->id;
            $admin->is_protected = false;

            $admin->save();
        }, 3);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' =>
                'Operator Admin account created successfully.',
        ]);

        return redirect()->route(
            'superadmin.operator-admins.index'
        );
    }

    /**
     * Assign an existing, unassigned Admin account.
     *
     * Never changes passwords, roles, packages,
     * bookings, or existing operator assignments.
     */
    public function assign(
        Request $request,
        User $admin
    ): RedirectResponse {
        $this->authorizeSuperadmin($request);

        $validated = $request->validate([
            'tour_operator_id' => [
                'required',
                'integer',
                Rule::exists('tour_operators', 'id')
                    ->where('is_active', true),
            ],
        ]);

        DB::transaction(function () use ($admin, $validated): void {
            $account = User::query()
                ->whereKey($admin->id)
                ->lockForUpdate()
                ->firstOrFail();

            // Never convert Tourist or Super Admin accounts.
            if ($account->role?->name !== 'Admin') {
                throw ValidationException::withMessages([
                    'tour_operator_id' =>
                        'Only Admin accounts can be assigned to a tour operator.',
                ]);
            }

            // Prevent accidental reassignment across businesses.
            if ($account->tour_operator_id !== null) {
                throw ValidationException::withMessages([
                    'tour_operator_id' =>
                        'This Admin is already assigned. Existing assignments cannot be overwritten here.',
                ]);
            }

            $operator = TourOperator::query()
                ->whereKey($validated['tour_operator_id'])
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();

            if ($operator === null) {
                throw ValidationException::withMessages([
                    'tour_operator_id' =>
                        'Select an active tour operator.',
                ]);
            }

            $account->tourOperator()->associate($operator);
            $account->save();
        }, 3);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' =>
                'Existing Admin assigned successfully. The account can now access its Operator portal.',
        ]);

        return redirect()->route(
            'superadmin.operator-admins.index'
        );
    }

    private function authorizeSuperadmin(Request $request): void
    {
        abort_unless(
            $request->user()?->role?->name === 'Superadmin',
            403
        );
    }
}
