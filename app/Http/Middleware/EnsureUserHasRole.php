<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Handle an incoming request.
     */
    public function handle(
        Request $request,
        Closure $next,
        string ...$roles
    ): Response {

        /*
        |--------------------------------------------------------------------------
        | CHECK AUTHENTICATED USER
        |--------------------------------------------------------------------------
        */

        $user = $request->user();

        if (! $user) {
            abort(401, 'Unauthenticated.');
        }

        /*
        |--------------------------------------------------------------------------
        | CHECK ROLE
        |--------------------------------------------------------------------------
        */

        $userRole = $user->role?->name;

        if (
            ! $userRole ||
            ! in_array($userRole, $roles, true)
        ) {
            abort(403, 'Unauthorized.');
        }

        /*
        |--------------------------------------------------------------------------
        | VALIDATE TOUR OPERATOR ADMIN
        |--------------------------------------------------------------------------
        |
        | Every Admin must belong to an active
        | tour operator.
        |
        | Superadmins and tourists are not required
        | to belong to a specific tour operator.
        |
        */

        if ($userRole === 'Admin') {

            $operator = $user->tourOperator;
            $registrationRoute = $request->routeIs(
                'operator.business.*',
                'operator.settings.*'
            );

            if ($registrationRoute) {
                return $next($request);
            }

            if ($operator === null || ! $operator->canManageRecords()) {
                abort(
                    403,
                    'Your business is not approved yet. Open My Business to view the application status.'
                );
            }

            if (
                ! $operator->acceptsBookings()
                && $request->routeIs(
                    'operator.walk-ins.store',
                    'operator.packages.store',
                    'operator.packages.publish'
                )
            ) {
                abort(403, 'This business cannot accept new bookings while it is suspended.');
            }
        }

        return $next($request);
    }
}
