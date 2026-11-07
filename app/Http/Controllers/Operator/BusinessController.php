<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\PackageOwnershipRequest;
use App\Models\TourOperator;
use App\Models\User;
use App\Notifications\OperatorBusinessSubmittedNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class BusinessController extends Controller
{
    public function edit(Request $request): Response
    {
        $admin = $request->user();
        abort_unless($admin?->role?->name === 'Admin', 403);

        $operator = $admin->tourOperator;
        $unassignedPackages = [];

        if ($operator?->acceptsBookings()) {
            $unassignedPackages = Package::query()
                ->withoutGlobalScope('tour_operator')
                ->whereNull('tour_operator_id')
                ->orderBy('package_name')
                ->get(['id', 'package_name'])
                ->map(fn (Package $package): array => [
                    'id' => $package->id,
                    'package_name' => $package->package_name,
                ])
                ->all();
        }

        return Inertia::render('operator/Business', [
            'business' => $operator === null ? null : [
                'id' => $operator->id,
                'name' => $operator->name,
                'contact_email' => $operator->contact_email,
                'contact_phone' => $operator->contact_phone,
                'description' => $operator->description,
                'approval_status' => $operator->approval_status,
                'status_label' => $operator->statusLabel(),
                'rejection_reason' => $operator->rejection_reason,
                'packages' => $operator->packages()
                    ->orderBy('package_name')
                    ->get(['id', 'package_name'])
                    ->map(fn (Package $package): array => [
                        'id' => $package->id,
                        'package_name' => $package->package_name,
                    ])
                    ->all(),
            ],
            'unassignedPackages' => $unassignedPackages,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $admin = $request->user();
        abort_unless($admin?->role?->name === 'Admin', 403);

        if ($admin->tour_operator_id !== null) {
            throw ValidationException::withMessages([
                'name' => 'This account is already linked to a tour operator business.',
            ]);
        }

        $validated = $this->validatedBusiness($request);

        $operator = DB::transaction(function () use ($validated, $admin): TourOperator {
            $lockedAdmin = User::query()->whereKey($admin->id)->lockForUpdate()->firstOrFail();

            if ($lockedAdmin->tour_operator_id !== null) {
                throw ValidationException::withMessages([
                    'name' => 'This account is already linked to a tour operator business.',
                ]);
            }

            $operator = TourOperator::query()->create([
                'name' => $validated['name'],
                'slug' => $this->uniqueSlug($validated['name']),
                'contact_email' => $validated['contact_email'],
                'contact_phone' => $validated['contact_phone'],
                'description' => $validated['description'] ?? null,
                'is_active' => false,
                'approval_status' => TourOperator::STATUS_PENDING,
                'rejection_reason' => null,
            ]);

            $lockedAdmin->tourOperator()->associate($operator);
            $lockedAdmin->save();

            return $operator;
        });

        $this->notifySuperadmins($operator);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Business registration submitted for approval.',
        ]);

        return redirect()->route('operator.business.edit');
    }

    public function update(Request $request): RedirectResponse
    {
        $admin = $request->user();
        abort_unless($admin?->role?->name === 'Admin', 403);

        $operator = $admin->tourOperator;
        abort_unless($operator !== null, 404);

        if ((int) $admin->tour_operator_id !== (int) $operator->id) {
            abort(403);
        }

        $validated = $this->validatedBusiness($request, $operator);

        $resubmitted = $operator->approval_status === TourOperator::STATUS_REJECTED;

        $operator->fill([
            'name' => $validated['name'],
            'contact_email' => $validated['contact_email'],
            'contact_phone' => $validated['contact_phone'],
            'description' => $validated['description'] ?? null,
        ]);

        if ($resubmitted) {
            $operator->approval_status = TourOperator::STATUS_PENDING;
            $operator->is_active = false;
            $operator->rejection_reason = null;
        }

        $operator->save();

        if ($resubmitted) {
            $this->notifySuperadmins($operator);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $resubmitted
                ? 'Business registration resubmitted.'
                : 'Business details saved.',
        ]);

        return redirect()->route('operator.business.edit');
    }

    public function requestPackage(Request $request): RedirectResponse
    {
        $admin = $request->user();
        $operator = $admin?->tourOperator;

        abort_unless(
            $admin?->role?->name === 'Admin' && $operator?->acceptsBookings(),
            403
        );

        $validated = $request->validate([
            'package_id' => ['required', 'integer', Rule::exists('packages', 'id')],
        ]);

        DB::transaction(function () use ($validated, $operator, $admin): void {
            $package = Package::query()
                ->withoutGlobalScope('tour_operator')
                ->whereKey($validated['package_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if ($package->tour_operator_id !== null) {
                throw ValidationException::withMessages([
                    'package_id' => 'This package already belongs to a tour operator.',
                ]);
            }

            $pending = PackageOwnershipRequest::query()
                ->where('package_id', $package->id)
                ->where('tour_operator_id', $operator->id)
                ->where('status', PackageOwnershipRequest::STATUS_PENDING)
                ->exists();

            if ($pending) {
                throw ValidationException::withMessages([
                    'package_id' => 'A request for this package is already pending.',
                ]);
            }

            PackageOwnershipRequest::query()->create([
                'package_id' => $package->id,
                'tour_operator_id' => $operator->id,
                'requested_by' => $admin->id,
                'status' => PackageOwnershipRequest::STATUS_PENDING,
            ]);
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Package ownership request sent to the Super Admin.',
        ]);

        return redirect()->route('operator.business.edit');
    }

    /**
     * @return array{name: string, contact_email: string, contact_phone: string, description: ?string}
     */
    private function validatedBusiness(Request $request, ?TourOperator $operator = null): array
    {
        return $request->validate([
            'name' => [
                'required',
                'string',
                'min:3',
                'max:150',
                Rule::unique('tour_operators', 'name')->ignore($operator?->id),
            ],
            'contact_email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('tour_operators', 'contact_email')->ignore($operator?->id),
            ],
            'contact_phone' => ['required', 'string', 'max:30'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::substr(Str::slug($name), 0, 170) ?: 'operator';
        $slug = $base;

        while (TourOperator::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.Str::lower(Str::random(6));
        }

        return $slug;
    }

    private function notifySuperadmins(TourOperator $operator): void
    {
        $superadmins = User::query()
            ->whereHas('role', fn ($query) => $query->where('name', 'Superadmin'))
            ->get();

        if ($superadmins->isNotEmpty()) {
            Notification::send($superadmins, new OperatorBusinessSubmittedNotification($operator));
        }
    }
}
