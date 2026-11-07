<?php

namespace App\Http\Middleware;

use App\Models\Booking;
use App\Models\Package;
use App\Models\Payment;
use App\Models\PickupLocation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /** @return array<string, mixed> */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),

            'name' => config('app.name'),

            'auth' => [
                'user' => $request->user()?->load('role'),
            ],

            'badgeCounts' => fn () =>
                $this->getBadgeCounts($request),

            'sidebarOpen' =>
                ! $request->hasCookie('sidebar_state')
                || $request->cookie('sidebar_state') === 'true',

            // Preserve the Step 2 trip outcome monitor.
            'tripMonitor' => fn () =>
                $this->getTripMonitor($request),
        ];
    }

    /**
     * Show trip outcomes only to authorized accounts.
     *
     * @return array<string, mixed>|null
     */
    private function getTripMonitor(Request $request): ?array
    {
        $user = $request->user();
        $role = $user?->role?->name;

        if (! in_array(
            $role,
            ['User', 'Admin', 'Superadmin'],
            true
        )) {
            return null;
        }

        $detail = $request->routeIs(
            'user.bookings.show',
            'operator.bookings.show',
            'operator.payments.show',
            'admin.bookings.show'
        );

        $list = $request->routeIs(
            'user.bookings.index',
            'user.history',
            'operator.bookings.index',
            'admin.bookings.index'
        );

        if (! $detail && ! $list) {
            return null;
        }

        $query = DB::table('booking_trip_records as trips')
            ->join(
                'bookings as bookings',
                'bookings.id',
                '=',
                'trips.booking_id'
            )
            ->join(
                'tour_dates as dates',
                'dates.id',
                '=',
                'bookings.tour_date_id'
            )
            ->join(
                'packages as packages',
                'packages.id',
                '=',
                'dates.package_id'
            );

        /*
         * Only authorized records can be returned.
         */
        if ($role === 'User') {
            $query->where(
                'bookings.user_id',
                (int) $user->id
            );
        } elseif ($role === 'Admin') {
            $operator = $user->tourOperator;

            if (
                $operator === null ||
                ! (bool) $operator->is_active
            ) {
                return null;
            }

            $query->where(
                'packages.tour_operator_id',
                (int) $operator->id
            );
        }

        /*
         * Specific booking detail.
         */
        if ($detail) {
            $parameter = $request->route('booking');

            $bookingId = $parameter instanceof Booking
                ? (int) $parameter->id
                : (
                    is_numeric($parameter)
                        ? (int) $parameter
                        : 0
                );

            if ($bookingId < 1) {
                return null;
            }

            $row = $query
                ->where('bookings.id', $bookingId)
                ->select([
                    'bookings.id as booking_id',
                    'bookings.booking_status',
                    'trips.outcome',
                    'trips.notes',
                    'trips.checked_in_at',
                    'trips.recorded_at',
                ])
                ->first();

            if ($row === null) {
                return null;
            }

            return [
                'mode' => 'detail',

                'record' => [
                    'booking_id' => (int) $row->booking_id,

                    'booking_status' =>
                        (string) $row->booking_status,

                    'outcome' => $row->outcome,

                    // Staff notes remain private.
                    'notes' => $role === 'User'
                        ? null
                        : $row->notes,

                    'checked_in_at' =>
                        $row->checked_in_at,

                    'recorded_at' =>
                        $row->recorded_at,
                ],
            ];
        }

        /*
         * Recent outcomes on booking overview pages.
         */
        $rows = $query
            ->whereNotNull('trips.outcome')
            ->select([
                'bookings.id as booking_id',
                'bookings.booking_status',
                'trips.outcome',
                'trips.recorded_at',
            ])
            ->orderByDesc('trips.recorded_at')
            ->limit(12)
            ->get()
            ->map(fn ($row): array => [
                'booking_id' => (int) $row->booking_id,

                'booking_status' =>
                    (string) $row->booking_status,

                'outcome' => (string) $row->outcome,

                'recorded_at' => $row->recorded_at,
            ])
            ->all();

        return count($rows) > 0
            ? [
                'mode' => 'list',
                'records' => $rows,
            ]
            : null;
    }

    /**
     * Correct badge counts according to account role.
     *
     * Superadmin: all platform records.
     * Admin: records belonging to their own Tour Operator.
     * User: their own pending bookings.
     *
     * @return array<string, int>|null
     */
    protected function getBadgeCounts(
        Request $request
    ): ?array {
        $user = $request->user();

        if ($user === null) {
            return null;
        }

        $roleName = $user->role?->name;

        /*
         * SUPER ADMIN — PLATFORM-WIDE COUNTS
         */
        if ($roleName === 'Superadmin') {
            return [
                'pending_bookings' => Booking::query()
                    ->where('is_archived', false)
                    ->where('booking_status', 'pending')
                    ->count(),

                'inactive_packages' => Package::query()
                    ->where('status', 'inactive')
                    ->count(),

                'inactive_pickup_locations' =>
                    PickupLocation::query()
                        ->where('status', 'inactive')
                        ->count(),

                'pending_payments' => Payment::query()
                    ->where('payment_status', 'pending')
                    ->count(),

                'archived_bookings' => Booking::query()
                    ->where('is_archived', true)
                    ->count(),
            ];
        }

        /*
         * OPERATOR / ADMIN — OWN BUSINESS ONLY
         */
        if ($roleName === 'Admin') {
            $operator = $user->tourOperator;

            if (
                $operator === null ||
                ! (bool) $operator->is_active
            ) {
                return [
                    'pending_bookings' => 0,
                    'inactive_packages' => 0,
                    'inactive_pickup_locations' => 0,
                    'pending_payments' => 0,
                    'archived_bookings' => 0,
                ];
            }

            $operatorId = (int) $operator->id;

            $ownedPackage = fn ($query) =>
                $query->where(
                    'tour_operator_id',
                    $operatorId
                );

            return [
                'pending_bookings' => Booking::query()
                    ->whereHas(
                        'tourDate.package',
                        $ownedPackage
                    )
                    ->where('is_archived', false)
                    ->where('booking_status', 'pending')
                    ->count(),

                'inactive_packages' => Package::query()
                    ->where('tour_operator_id', $operatorId)
                    ->where('status', 'inactive')
                    ->count(),

                'inactive_pickup_locations' =>
                    PickupLocation::query()
                        ->where(
                            'tour_operator_id',
                            $operatorId
                        )
                        ->where('status', 'inactive')
                        ->count(),

                'pending_payments' => Payment::query()
                    ->whereHas(
                        'booking.tourDate.package',
                        $ownedPackage
                    )
                    ->where('payment_status', 'pending')
                    ->count(),

                'archived_bookings' => Booking::query()
                    ->whereHas(
                        'tourDate.package',
                        $ownedPackage
                    )
                    ->where('is_archived', true)
                    ->count(),
            ];
        }

        /*
         * TOURIST — OWN PENDING BOOKINGS
         */
        if ($roleName === 'User') {
            return [
                'pending_bookings' => Booking::query()
                    ->where('user_id', $user->id)
                    ->where('is_archived', false)
                    ->where('booking_status', 'pending')
                    ->count(),

                'inactive_packages' => 0,
                'inactive_pickup_locations' => 0,
                'pending_payments' => 0,
                'archived_bookings' => 0,
            ];
        }

        return null;
    }
}
