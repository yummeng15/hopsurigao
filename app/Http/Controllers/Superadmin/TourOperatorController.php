<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\PackageOwnershipRequest;
use App\Models\TourOperator;
use App\Models\User;
use App\Notifications\OperatorBusinessDecisionNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TourOperatorController extends Controller
{
    /**
     * Display tour operators and available assignments.
     */
    public function index(Request $request): Response
    {
        $bookingCounts = DB::table('bookings')
            ->join('tour_dates', 'tour_dates.id', '=', 'bookings.tour_date_id')
            ->join('packages', 'packages.id', '=', 'tour_dates.package_id')
            ->whereNotNull('packages.tour_operator_id')
            ->groupBy('packages.tour_operator_id')
            ->selectRaw('packages.tour_operator_id as operator_id, COUNT(*) as total')
            ->pluck('total', 'operator_id');

        $operators = TourOperator::query()
            ->with([
                'users:id,name,tour_operator_id',
                'packages:id,package_name,tour_operator_id',
            ])
            ->when($request->filled('status'), function ($query) use ($request): void {
                $query->where('approval_status', $request->string('status')->toString());
            })
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = '%'.$request->string('search')->toString().'%';
                $query->where(function ($inner) use ($search): void {
                    $inner->where('name', 'like', $search)
                        ->orWhere('contact_email', 'like', $search);
                });
            })
            ->orderBy('name')
            ->get()
            ->map(function (TourOperator $operator) use ($bookingCounts) {
                return [
                    'id' => $operator->id,
                    'name' => $operator->name,
                    'slug' => $operator->slug,
                    'description' => $operator->description,
                    'contact_email' => $operator->contact_email,
                    'contact_phone' => $operator->contact_phone,
                    'is_active' => $operator->is_active,
                    'approval_status' => $operator->approval_status,
                    'status_label' => $operator->statusLabel(),
                    'rejection_reason' => $operator->rejection_reason,
                    'bookings_count' => (int) ($bookingCounts[$operator->id] ?? 0),

                    'admins' => $operator->users
                        ->map(function (User $user) {
                            return [
                                'id' => $user->id,
                                'name' => $user->name,
                            ];
                        })
                        ->values()
                        ->all(),

                    'packages' => $operator->packages
                        ->map(function (Package $package) {
                            return [
                                'id' => $package->id,
                                'package_name' => $package->package_name,
                            ];
                        })
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();

        /*
         * Existing Admin accounts that have not yet
         * been assigned to a tour operator.
         */
        $availableAdmins = User::query()
            ->whereNull('tour_operator_id')
            ->whereHas('role', function ($query) {
                $query->where('name', 'Admin');
            })
            ->orderBy('name')
            ->get([
                'id',
                'name',
            ]);

        /*
         * Existing packages without an operator.
         */
        $availablePackages = Package::query()
            ->whereNull('tour_operator_id')
            ->orderBy('package_name')
            ->get([
                'id',
                'package_name',
            ]);

        $packageAssignments = Package::query()
            ->with('tourOperator.users.role')
            ->orderBy('package_name')
            ->get()
            ->map(function (Package $package): array {
                $assignment = $package->assignmentSummary();

                return [
                    'id' => $package->id,
                    'package_name' => $package->package_name,
                    'operator_id' => $package->tour_operator_id,
                    'operator_name' => $assignment['operator_name'],
                    'admin_name' => $assignment['admin_name'],
                    'assignment_status' => $assignment['assignment_status'],
                ];
            })
            ->values()
            ->all();

        $ownershipRequests = PackageOwnershipRequest::query()
            ->with([
                'package:id,package_name,tour_operator_id',
                'tourOperator:id,name',
                'requester:id,name',
            ])
            ->where('status', PackageOwnershipRequest::STATUS_PENDING)
            ->latest()
            ->get()
            ->map(fn (PackageOwnershipRequest $ownershipRequest): array => [
                'id' => $ownershipRequest->id,
                'package_name' => $ownershipRequest->package?->package_name,
                'operator_name' => $ownershipRequest->tourOperator?->name,
                'admin_name' => $ownershipRequest->requester?->name,
            ])
            ->all();

        return Inertia::render(
            'superadmin/operators/Index',
            [
                'operators' => $operators,
                'packageAssignments' => $packageAssignments,
                'ownershipRequests' => $ownershipRequests,
                'filters' => [
                    'search' => $request->string('search')->toString(),
                    'status' => $request->string('status')->toString(),
                ],
            ]
        );
    }

    public function decide(Request $request, TourOperator $operator): RedirectResponse
    {
        abort_unless($request->user()?->role?->name === 'Superadmin', 403);

        $validated = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject', 'suspend', 'reactivate'])],
            'rejection_reason' => ['nullable', 'string', 'max:1000', 'required_if:decision,reject'],
        ]);

        $decision = $validated['decision'];

        if ($decision === 'approve' && $operator->approval_status !== TourOperator::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'decision' => 'Only a pending registration can be approved.',
            ]);
        }

        if ($decision === 'reject' && $operator->approval_status !== TourOperator::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'decision' => 'Only a pending registration can be rejected.',
            ]);
        }

        if ($decision === 'suspend' && $operator->approval_status !== TourOperator::STATUS_ACTIVE) {
            throw ValidationException::withMessages([
                'decision' => 'Only an active business can be suspended.',
            ]);
        }

        if ($decision === 'reactivate' && $operator->approval_status !== TourOperator::STATUS_SUSPENDED) {
            throw ValidationException::withMessages([
                'decision' => 'Only a suspended business can be reactivated.',
            ]);
        }

        if ($decision === 'approve' || $decision === 'reactivate') {
            $operator->approval_status = TourOperator::STATUS_ACTIVE;
            $operator->is_active = true;
            $operator->rejection_reason = null;
        }

        if ($decision === 'reject') {
            $operator->approval_status = TourOperator::STATUS_REJECTED;
            $operator->is_active = false;
            $operator->rejection_reason = $validated['rejection_reason'];
        }

        if ($decision === 'suspend') {
            $operator->approval_status = TourOperator::STATUS_SUSPENDED;
            $operator->is_active = false;
        }

        $operator->save();

        if (in_array($decision, ['approve', 'reject'], true)) {
            $admins = $operator->users()
                ->whereHas('role', fn ($query) => $query->where('name', 'Admin'))
                ->get();

            if ($admins->isNotEmpty()) {
                Notification::send($admins, new OperatorBusinessDecisionNotification($operator, $decision === 'approve' ? 'approved' : 'rejected'));
            }
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Tour operator status updated.',
        ]);

        return redirect()->route('superadmin.operators.index');
    }

    public function reviewPackageRequest(Request $request, PackageOwnershipRequest $ownershipRequest): RedirectResponse
    {
        abort_unless($request->user()?->role?->name === 'Superadmin', 403);

        $validated = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject'])],
        ]);

        if ($ownershipRequest->status !== PackageOwnershipRequest::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'decision' => 'This package request has already been reviewed.',
            ]);
        }

        DB::transaction(function () use ($validated, $ownershipRequest, $request): void {
            $locked = PackageOwnershipRequest::query()
                ->whereKey($ownershipRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($validated['decision'] === 'reject') {
                $locked->status = PackageOwnershipRequest::STATUS_REJECTED;
                $locked->reviewed_by = $request->user()->id;
                $locked->save();

                return;
            }

            $package = Package::query()
                ->whereKey($locked->package_id)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                $package->tour_operator_id !== null
                && (int) $package->tour_operator_id !== (int) $locked->tour_operator_id
            ) {
                throw ValidationException::withMessages([
                    'decision' => 'This package already belongs to another business.',
                ]);
            }

            $operator = TourOperator::query()->whereKey($locked->tour_operator_id)->first();

            if ($operator === null || ! $operator->acceptsBookings()) {
                throw ValidationException::withMessages([
                    'decision' => 'The requesting business is not active.',
                ]);
            }

            Package::query()
                ->whereKey($package->id)
                ->update([
                    'tour_operator_id' => $locked->tour_operator_id,
                ]);

            $locked->status = PackageOwnershipRequest::STATUS_APPROVED;
            $locked->reviewed_by = $request->user()->id;
            $locked->save();

            PackageOwnershipRequest::query()
                ->where('package_id', $package->id)
                ->whereKeyNot($locked->id)
                ->where('status', PackageOwnershipRequest::STATUS_PENDING)
                ->update([
                    'status' => PackageOwnershipRequest::STATUS_REJECTED,
                    'review_note' => 'This package was assigned to another business.',
                    'reviewed_by' => $request->user()->id,
                ]);
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Package ownership request reviewed.',
        ]);

        return redirect()->route('superadmin.operators.index');
    }

    /**
     * Register a tour operator.
     *
     * Optionally assign an existing Admin account
     * and existing unassigned packages.
     */
    public function store(Request $request): RedirectResponse
    {
        abort_unless(
            $request->user()?->role?->name === 'Superadmin',
            403
        );

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'min:3',
                'max:150',
                Rule::unique('tour_operators', 'name'),
            ],

            'description' => [
                'nullable',
                'string',
                'max:2000',
            ],

            'contact_email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('tour_operators', 'contact_email'),
            ],

            'is_active' => [
                'required',
                'boolean',
            ],

            'contact_phone' => [
                'nullable',
                'string',
                'max:30',
            ],

            'admin_user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id'),
            ],

            'package_ids' => [
                'sometimes',
                'array',
            ],

            'package_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('packages', 'id'),
            ],
        ]);

        /*
         * A transaction ensures the operator and
         * its assignments are saved together.
         */
        DB::transaction(function () use ($validated) {

            $admin = null;

            /*
             * Verify that the selected Admin account
             * is really an unassigned Admin.
             */
            if (! empty($validated['admin_user_id'])) {

                $admin = User::query()
                    ->with('role')
                    ->whereKey($validated['admin_user_id'])
                    ->lockForUpdate()
                    ->first();

                if (
                    ! $admin ||
                    $admin->role?->name !== 'Admin' ||
                    $admin->tour_operator_id !== null
                ) {
                    throw ValidationException::withMessages([
                        'admin_user_id' => 'The selected Admin is not available for assignment.',
                    ]);
                }
            }

            /*
             * Verify package ownership before
             * assigning any records.
             */
            $packageIds = array_map(
                'intval',
                $validated['package_ids'] ?? []
            );

            if (count($packageIds) > 0) {

                $availableIds = Package::query()
                    ->whereIn('id', $packageIds)
                    ->whereNull('tour_operator_id')
                    ->lockForUpdate()
                    ->pluck('id')
                    ->all();

                if (count($availableIds) !== count($packageIds)) {

                    throw ValidationException::withMessages([
                        'package_ids' => 'One or more selected packages are already assigned.',
                    ]);
                }
            }

            /*
             * Generate a unique operator slug.
             */
            $baseSlug = Str::substr(
                Str::slug($validated['name']),
                0,
                170
            );

            if ($baseSlug === '') {
                $baseSlug = 'operator';
            }

            $slug = $baseSlug;

            while (
                TourOperator::query()
                    ->where('slug', $slug)
                    ->exists()
            ) {
                $slug = $baseSlug
                    .'-'
                    .Str::lower(Str::random(6));
            }

            /*
             * Create the tour operator.
             */
            $operator = TourOperator::create([
                'name' => $validated['name'],
                'slug' => $slug,
                'description' => $validated['description'] ?? null,
                'contact_email' => $validated['contact_email'] ?? null,
                'contact_phone' => $validated['contact_phone'] ?? null,
                'is_active' => (bool) $validated['is_active'],
            ]);

            /*
             * Assign the existing Admin account.
             */
            if ($admin) {

                $admin->tourOperator()->associate(
                    $operator
                );

                $admin->save();
            }

            /*
             * Assign selected existing packages.
             */
            if (count($packageIds) > 0) {

                Package::query()
                    ->whereIn('id', $packageIds)
                    ->whereNull('tour_operator_id')
                    ->update([
                        'tour_operator_id' => $operator->id,
                    ]);
            }
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Tour operator registered successfully.',
        ]);

        return redirect()->route(
            'superadmin.operators.index'
        );
    }

    /**
     * Link an unassigned Admin and unassigned packages to an existing business.
     * Packages and admins that already belong to a business are left unchanged.
     */
    public function assign(Request $request, TourOperator $operator): RedirectResponse
    {
        abort_unless(
            $request->user()?->role?->name === 'Superadmin',
            403
        );

        $validated = $request->validate([
            'admin_user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id'),
            ],
            'package_ids' => [
                'sometimes',
                'array',
            ],
            'package_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('packages', 'id'),
            ],
        ]);

        DB::transaction(function () use ($validated, $operator): void {
            $operator = TourOperator::query()
                ->whereKey($operator->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! empty($validated['admin_user_id'])) {
                $admin = User::query()
                    ->with('role')
                    ->whereKey($validated['admin_user_id'])
                    ->lockForUpdate()
                    ->first();

                if (
                    ! $admin ||
                    $admin->role?->name !== 'Admin' ||
                    $admin->tour_operator_id !== null
                ) {
                    throw ValidationException::withMessages([
                        'admin_user_id' => 'The selected Admin is not available for assignment.',
                    ]);
                }

                $admin->tourOperator()->associate($operator);
                $admin->save();
            }

            $packageIds = array_map('intval', $validated['package_ids'] ?? []);

            if (count($packageIds) === 0) {
                return;
            }

            $availableIds = Package::query()
                ->whereIn('id', $packageIds)
                ->whereNull('tour_operator_id')
                ->lockForUpdate()
                ->pluck('id')
                ->all();

            if (count($availableIds) !== count($packageIds)) {
                throw ValidationException::withMessages([
                    'package_ids' => 'One or more selected packages are already assigned.',
                ]);
            }

            Package::query()
                ->whereIn('id', $packageIds)
                ->whereNull('tour_operator_id')
                ->update([
                    'tour_operator_id' => $operator->id,
                ]);
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Tour operator assignments saved.',
        ]);

        return redirect()->route('superadmin.operators.index');
    }

    /**
     * Assign or move existing packages onto one active Tour Operator.
     * Package details, schedules, boats, prices, and bookings stay in place.
     */
    public function assignPackages(Request $request): RedirectResponse
    {
        abort_unless(
            $request->user()?->role?->name === 'Superadmin',
            403
        );

        $validated = $request->validate([
            'tour_operator_id' => [
                'required',
                'integer',
                Rule::exists('tour_operators', 'id'),
            ],
            'package_ids' => [
                'required',
                'array',
                'min:1',
            ],
            'package_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('packages', 'id'),
            ],
        ]);

        DB::transaction(function () use ($validated): void {
            $operator = TourOperator::query()
                ->whereKey($validated['tour_operator_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if (! $operator->is_active) {
                throw ValidationException::withMessages([
                    'tour_operator_id' => 'Choose an active tour operator business.',
                ]);
            }

            $packageIds = array_map('intval', $validated['package_ids']);

            $lockedIds = Package::query()
                ->whereIn('id', $packageIds)
                ->lockForUpdate()
                ->pluck('id');

            if ($lockedIds->count() !== count($packageIds)) {
                throw ValidationException::withMessages([
                    'package_ids' => 'One or more selected packages could not be assigned.',
                ]);
            }

            Package::query()
                ->whereIn('id', $packageIds)
                ->update([
                    'tour_operator_id' => $operator->id,
                ]);
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Package operator assignments saved.',
        ]);

        return redirect()->route('superadmin.operators.index');
    }
}
