<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ProtectLegacyAdminRoutes
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $user = $request->user();
        $role = $user?->role?->name;

        abort_unless(
            in_array($role, ['Admin', 'Superadmin'], true),
            403
        );

        // Block unsafe old booking/payment actions for both roles.
        abort_if(
            $request->routeIs(
                'admin.bookings.create',
                'admin.bookings.store',
                'admin.bookings.edit',
                'admin.bookings.update',
                'admin.bookings.destroy',
                'admin.bookings.onsite-dates',
                'admin.bookings.confirm-payment',
                'admin.bookings.balance'
            ),
            403,
            'Use the verified booking and payment workflows.'
        );

        if ($role === 'Superadmin') {
            abort_if(
                $request->routeIs(
                    'admin.packages.create',
                    'admin.packages.store',
                    'admin.packages.edit',
                    'admin.packages.update',
                    'admin.packages.destroy',
                ),
                403,
                'Package oversight is view only.'
            );

            abort_if(
                $request->routeIs(
                    'admin.users.create',
                    'admin.users.store',
                    'admin.users.edit',
                    'admin.users.update',
                    'admin.users.destroy',
                ),
                403,
                'User oversight is view only.'
            );

            abort_if(
                $request->routeIs('admin.refunds.store'),
                403,
                'Refunds are recorded by the Operator Admin. Payment oversight is view only.'
            );

            return $next($request);
        }

        abort_unless(
            $user->tourOperator?->is_active === true,
            403,
            'You must belong to an active tour operator.'
        );

        if ($request->routeIs('admin.dashboard')) {
            return redirect()->route('operator.dashboard');
        }

        // Old notification / bookmark links still point at /admin/*.
        // Send operators to the matching Tour Operator portal page.
        if ($request->routeIs('admin.bookings.show')) {
            return redirect()->route(
                'operator.bookings.show',
                $request->route('booking')
            );
        }

        if ($request->routeIs('admin.bookings.index')) {
            return redirect()->route('operator.bookings.index');
        }

        if ($request->routeIs('admin.bookings.calendar')) {
            return redirect()->route('operator.bookings.calendar');
        }

        if ($request->routeIs('admin.payments.index')) {
            return redirect()->route('operator.payments.index');
        }

        if ($request->routeIs('admin.booking-feedback.index')) {
            return redirect()->route('operator.operations.feedback');
        }

        // Operators must not use platform-wide Admin controllers.
        abort_if(
            $request->routeIs(
                'admin.users.*',
                'admin.roles.*',
                'admin.permissions.*',
                'admin.refunds.*',
                'admin.packages.*',
                'admin.payments.*',
                'admin.bookings.*',
                'admin.archived-bookings.*',
                'admin.qrscanner',
                'admin.qrscanner.*',
                'admin.pickup-locations.*',
                'admin.boat-maintenance.*',
                'admin.weather-advisories.*',
                'admin.booking-feedback.*',
                'admin.reports.*'
            ),
            403,
            'Use your dedicated Tour Operator portal.'
        );

        // Other shared Admin routes require a separate audit.
        return $next($request);
    }
}
