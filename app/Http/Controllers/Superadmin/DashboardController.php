<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Role;
use App\Models\TourDate;
use App\Models\User;
use App\Models\WeatherForecast;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $today = now()->toDateString();
        $futureLimit = now()->addDays(30)->toDateString();

        // 1. User & Role Metrics
        $totalUsers = User::count();
        $verifiedUsers = User::whereNotNull('email_verified_at')->count();
        $unverifiedUsers = User::whereNull('email_verified_at')->count();
        $twoFactorUsers = User::whereNotNull('two_factor_confirmed_at')->count();
        $protectedUsers = User::where('is_protected', true)->count();

        $roles = Role::withCount(['users', 'permissions'])->get();
        $totalRoles = $roles->count();
        $totalPermissions = Permission::count();

        $superadminCount = User::whereHas(
            'role',
            fn ($q) => $q->where('name', 'Superadmin')
        )->count();

        $adminCount = User::whereHas(
            'role',
            fn ($q) => $q->where('name', 'Admin')
        )->count();

        $regularUserCount = User::whereHas(
            'role',
            fn ($q) => $q->where('name', 'User')
        )->count();

        // 2. Booking & Revenue Metrics
        $totalBookings = Booking::count();

        $confirmedBookings = Booking::where(
            'booking_status',
            'confirmed'
        )->count();

        $pendingBookings = Booking::where(
            'booking_status',
            'pending'
        )->count();

        $completedBookings = Booking::where(
            'booking_status',
            'completed'
        )->count();

        $cancelledBookings = Booking::where(
            'booking_status',
            'cancelled'
        )->count();

        /*
         * VERIFIED REVENUE
         *
         * Include only paid and partial payments.
         *
         * New online GCash payments are the full booking
         * amount. Older partial receipts stay unchanged
         * and are still counted separately from any later
         * cash balance.
         *
         * Pending/failed/cancelled payments are not
         * received revenue.
         */
        $totalRevenue = (float) Payment::whereIn(
            'payment_status',
            ['paid', 'partial']
        )->sum('amount');

        // Pending amounts are NOT received revenue.
        $pendingRevenue = (float) Payment::where(
            'payment_status',
            'pending'
        )->sum('amount');

        $totalGuests = (int) Booking::whereIn(
            'booking_status',
            ['pending', 'confirmed', 'completed']
        )->sum('number_of_guests');

        // 3. Packages & Upcoming Tours
        $totalPackages = Package::where(
            'status',
            'active'
        )->count();

        $upcomingTourDatesCount = TourDate::where(
            'tour_date',
            '>=',
            $today
        )->count();

        // 4. Weather Advisory Status Counts
        $upcomingTourDateIds = TourDate::whereBetween(
            'tour_date',
            [$today, $futureLimit]
        )->pluck('id');

        $forecasts = WeatherForecast::whereIn(
            'tour_date_id',
            $upcomingTourDateIds
        )->get();

        $weatherCounts = [
            'green' => $forecasts
                ->where('advisory_level', 'green')
                ->count(),

            'yellow' => $forecasts
                ->where('advisory_level', 'yellow')
                ->count(),

            'orange' => $forecasts
                ->where('advisory_level', 'orange')
                ->count(),

            'red' => $forecasts
                ->where('advisory_level', 'red')
                ->count(),
        ];

        // 5. Monthly Booking & Revenue Trends
        $monthlyTrends = [];
        $monthlyUserTrends = [];

        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);

            $year = $date->year;
            $month = $date->month;
            $monthLabel = $date->format('M Y');

            $monthBookings = Booking::whereYear(
                'created_at',
                $year
            )
                ->whereMonth('created_at', $month)
                ->count();

            /*
             * Record revenue in the month payment
             * was received, not when the pending
             * checkout was created.
             *
             * Historical records without paid_at
             * require reconciliation separately.
             */
            $monthRevenue = (float) Payment::whereIn(
                'payment_status',
                ['paid', 'partial']
            )
                ->whereNotNull('paid_at')
                ->whereYear('paid_at', $year)
                ->whereMonth('paid_at', $month)
                ->sum('amount');

            $monthGuests = (int) Booking::whereIn(
                'booking_status',
                ['pending', 'confirmed', 'completed']
            )
                ->whereYear('created_at', $year)
                ->whereMonth('created_at', $month)
                ->sum('number_of_guests');

            $monthlyTrends[] = [
                'month' => $monthLabel,
                'bookings' => $monthBookings,
                'revenue' => $monthRevenue,
                'guests' => $monthGuests,
            ];

            $newRegistrations = User::whereYear(
                'created_at',
                $year
            )
                ->whereMonth('created_at', $month)
                ->count();

            $verifiedRegistrations = User::whereYear(
                'created_at',
                $year
            )
                ->whereMonth('created_at', $month)
                ->whereNotNull('email_verified_at')
                ->count();

            $monthlyUserTrends[] = [
                'month' => $monthLabel,
                'registrations' => $newRegistrations,
                'verified' => $verifiedRegistrations,
            ];
        }

        // 6. Top Packages by Reservations
        $popularPackages = Package::where('status', 'active')
            ->withCount(['tourDates'])
            ->get()
            ->map(function (Package $pkg) {
                $tourDateIds = $pkg->tourDates()
                    ->pluck('id');

                $bookingsCount = Booking::whereIn(
                    'tour_date_id',
                    $tourDateIds
                )->count();

                $guestsCount = (int) Booking::whereIn(
                    'tour_date_id',
                    $tourDateIds
                )
                    ->whereIn(
                        'booking_status',
                        ['pending', 'confirmed', 'completed']
                    )
                    ->sum('number_of_guests');

                return [
                    'package_name' => $pkg->package_name,
                    'destination' => $pkg->destination,
                    'bookings_count' => $bookingsCount,
                    'guests_count' => $guestsCount,
                    'price' => (float) $pkg->price,
                ];
            })
            ->sortByDesc('bookings_count')
            ->take(5)
            ->values();

        // 7. Booking Status Distribution
        $statusDistribution = [
            [
                'status' => 'Confirmed',
                'count' => $confirmedBookings,
                'color' => '#10b981',
            ],
            [
                'status' => 'Pending',
                'count' => $pendingBookings,
                'color' => '#f59e0b',
            ],
            [
                'status' => 'Completed',
                'count' => $completedBookings,
                'color' => '#0ea5e9',
            ],
            [
                'status' => 'Cancelled',
                'count' => $cancelledBookings,
                'color' => '#ef4444',
            ],
        ];

        // 8. Weather Advisory Distribution
        $weatherDistribution = [
            [
                'level' => 'Suitable (Green)',
                'count' => $weatherCounts['green'],
                'color' => '#10b981',
            ],
            [
                'level' => 'Caution (Yellow)',
                'count' => $weatherCounts['yellow'],
                'color' => '#eab308',
            ],
            [
                'level' => 'Unfavorable (Orange)',
                'count' => $weatherCounts['orange'],
                'color' => '#f97316',
            ],
            [
                'level' => 'Dangerous (Red)',
                'count' => $weatherCounts['red'],
                'color' => '#ef4444',
            ],
        ];

        // 9. User Role Distribution
        $roleColors = [
            'Superadmin' => '#8b5cf6',
            'Admin' => '#0ea5e9',
            'User' => '#10b981',
        ];

        $roleDistribution = $roles->map(
            function (Role $role) use ($roleColors) {
                return [
                    'role' => $role->name,
                    'count' => $role->users_count,
                    'color' => $roleColors[$role->name]
                        ?? '#f59e0b',
                ];
            }
        )->values();

        // 10. Role Verification Statistics
        $roleVerificationStats = $roles->map(
            function (Role $role) {
                $verified = User::where(
                    'role_id',
                    $role->id
                )
                    ->whereNotNull('email_verified_at')
                    ->count();

                $unverified = User::where(
                    'role_id',
                    $role->id
                )
                    ->whereNull('email_verified_at')
                    ->count();

                $formattedName = match (
                    strtolower($role->name)
                ) {
                    'superadmin' => 'Superadmin',
                    'admin' => 'Admin',
                    'user' => 'Tourist (User)',
                    default => ucfirst($role->name),
                };

                return [
                    'role_name' => $formattedName,
                    'verified' => $verified,
                    'unverified' => $unverified,
                    'total' => $role->users_count,
                ];
            }
        )->values();

        // 11. Security Posture Distribution
        $securityDistribution = [
            [
                'status' => '2FA Enabled',
                'count' => $twoFactorUsers,
                'color' => '#10b981',
            ],
            [
                'status' => 'Email Verified',
                'count' => max(
                    0,
                    $verifiedUsers - $twoFactorUsers
                ),
                'color' => '#0ea5e9',
            ],
            [
                'status' => 'Unverified Email',
                'count' => $unverifiedUsers,
                'color' => '#f59e0b',
            ],
        ];

        // 12. Recent User Registrations
        $recentUsers = User::with('role')
            ->latest()
            ->take(10)
            ->get()
            ->map(function (User $user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role_name' => $user->role?->name
                        ?? 'No Role',
                    'is_protected' => $user->is_protected,
                    'is_verified' => ! is_null(
                        $user->email_verified_at
                    ),
                    'has_2fa' => ! is_null(
                        $user->two_factor_confirmed_at
                    ),
                    'created_at' => $user->created_at
                        ?->toIso8601String(),
                ];
            });

        // 13. Role & Permission Matrix Summary
        $roleSummary = $roles->map(
            function (Role $role) {
                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'description' => $role->description
                        ?? 'No description provided.',
                    'users_count' => $role->users_count,
                    'permissions_count' => $role->permissions_count,
                ];
            }
        );

        /*
         * 14. RECENT BOOKINGS — FINANCIAL FIX
         *
         * - Use original saved booking price.
         * - Sum all successful payments.
         * - Distinguish Partial vs Paid.
         * - Flag old estimated prices.
         */
        $recentBookings = Booking::with([
            'user',
            'tourDate.package',
            'payments',
        ])
            ->latest()
            ->take(10)
            ->get()
            ->map(function (Booking $booking) {
                /*
                 * Never recalculate a verified
                 * booking price using catalog price.
                 */
                $savedTotal = $booking->booked_total !== null
                    ? (float) $booking->booked_total
                    : null;

                $needsReview = $savedTotal === null
                    || $savedTotal <= 0
                    || $booking->price_snapshot_source
                        !== 'at_booking';

                /*
                 * Legacy fallback is an estimate.
                 * The payment badge will show Review.
                 */
                $displayTotal = $savedTotal
                    ?? $booking->packageTotal();

                /*
                 * amountReceived() counts successful
                 * paid and partial payment records.
                 */
                $received = $booking->amountReceived();

                $hasPending = $booking->payments->contains(
                    fn (Payment $payment): bool => $payment->payment_status === 'pending'
                );

                /*
                 * These rules match Super Admin
                 * Booking Details.
                 *
                 * Never use the first payment as the
                 * booking's overall payment status.
                 */
                $overallPaymentStatus = $needsReview
                    ? 'review'
                    : (
                        $received >= $savedTotal - 0.005
                            ? 'paid'
                            : (
                                $received > 0
                                    ? 'partial'
                                    : (
                                        $hasPending
                                            ? 'pending'
                                            : 'unpaid'
                                    )
                            )
                    );

                return [
                    'id' => $booking->id,
                    'user_name' => $booking->user?->name
                        ?? 'Guest Traveler',
                    'user_email' => $booking->user?->email
                        ?? '—',
                    'package_name' => $booking->tourDate
                        ?->package?->package_name ?? '—',
                    'destination' => $booking->tourDate
                        ?->package?->destination ?? '—',
                    'tour_date' => $booking->tourDate
                        ?->tour_date?->toDateString(),
                    'number_of_guests' => $booking->number_of_guests,

                    'booking_status' => $booking->booking_status,

                    'payment_status' => $overallPaymentStatus,

                    'total_amount' => round(
                        (float) $displayTotal,
                        2
                    ),

                    'amount_received' => round(
                        $received,
                        2
                    ),

                    'remaining_balance' => $savedTotal !== null
                            ? round(
                                max(
                                    0,
                                    $savedTotal - $received
                                ),
                                2
                            )
                            : null,

                    'price_needs_review' => $needsReview,

                    'created_at' => $booking->created_at
                        ?->toIso8601String(),
                ];
            });

        // 15. Upcoming Scheduled Tours
        $upcomingTours = TourDate::where(
            'tour_date',
            '>=',
            $today
        )
            ->whereHas(
                'package',
                fn ($q) => $q->where(
                    'status',
                    'active'
                )
            )
            ->with([
                'package',
                'weatherForecast',
            ])
            ->orderBy('tour_date')
            ->take(10)
            ->get()
            ->map(function (TourDate $td) {
                $bookedGuests = (int) $td->bookings()
                    ->whereIn(
                        'booking_status',
                        ['pending', 'confirmed']
                    )
                    ->sum('number_of_guests');

                return [
                    'id' => $td->id,
                    'tour_date' => $td->tour_date->toDateString(),
                    'package_name' => $td->package?->package_name ?? '—',
                    'destination' => $td->package?->destination ?? '—',
                    'capacity' => $td->capacity,
                    'booked_guests' => $bookedGuests,
                    'remaining_capacity' => max(
                        0,
                        $td->capacity - $bookedGuests
                    ),
                    'weather_forecast' => $td->weatherForecast,
                ];
            });

        return Inertia::render('superadmin/Dashboard', [
            'stats' => [
                'total_bookings' => $totalBookings,
                'confirmed_bookings' => $confirmedBookings,
                'pending_bookings' => $pendingBookings,
                'completed_bookings' => $completedBookings,
                'cancelled_bookings' => $cancelledBookings,
                'total_revenue' => $totalRevenue,
                'pending_revenue' => $pendingRevenue,
                'total_guests' => $totalGuests,
                'total_users' => $totalUsers,
                'total_packages' => $totalPackages,
                'upcoming_tours' => $upcomingTourDatesCount,
                'weather_advisories' => $weatherCounts,
                'verified_users' => $verifiedUsers,
                'unverified_users' => $unverifiedUsers,
                'two_factor_users' => $twoFactorUsers,
                'protected_users' => $protectedUsers,
                'superadmin_count' => $superadminCount,
                'admin_count' => $adminCount,
                'user_count' => $regularUserCount,
                'total_roles' => $totalRoles,
                'total_permissions' => $totalPermissions,
            ],

            'monthlyTrends' => $monthlyTrends,
            'monthlyUserTrends' => $monthlyUserTrends,
            'popularPackages' => $popularPackages,
            'statusDistribution' => $statusDistribution,
            'weatherDistribution' => $weatherDistribution,
            'roleDistribution' => $roleDistribution,
            'roleVerificationStats' => $roleVerificationStats,
            'securityDistribution' => $securityDistribution,
            'recentBookings' => $recentBookings,
            'upcomingTours' => $upcomingTours,
            'recentUsers' => $recentUsers,
            'roleSummary' => $roleSummary,
        ]);
    }
}
