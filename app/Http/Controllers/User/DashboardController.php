<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $userId = Auth::id();

        /*
         * Booking statistics.
         */
        $totalBookings = Booking::query()
            ->where('user_id', $userId)
            ->count();

        $pendingBookings = Booking::query()
            ->where('user_id', $userId)
            ->where('booking_status', 'pending')
            ->count();

        $confirmedBookings = Booking::query()
            ->where('user_id', $userId)
            ->where('booking_status', 'confirmed')
            ->count();

        $cancelledBookings = Booking::query()
            ->where('user_id', $userId)
            ->where('booking_status', 'cancelled')
            ->count();

        $completedBookings = Booking::query()
            ->where('user_id', $userId)
            ->where('booking_status', 'completed')
            ->count();

        /*
         * Upcoming bookings.
         *
         * Cancelled bookings are excluded.
         */
        $upcomingBookings = Booking::query()
            ->where('user_id', $userId)
            ->whereIn('booking_status', [
                'pending',
                'confirmed',
            ])
            ->whereHas('tourDate', function ($query) {
                $query->whereDate(
                    'tour_date',
                    '>=',
                    now()->toDateString()
                );
            })
            ->with([
                'tourDate.package',
                'tourDate.weatherForecast',
                'pickupSchedule.pickupLocation',
            ])
            ->get()
            ->sortBy(function ($booking) {
                return $booking->tourDate->tour_date;
            })
            ->take(5)
            ->values();

        /*
         * Next upcoming booking.
         */
        $nextBooking = $upcomingBookings->first();

        return Inertia::render(
            'user/Dashboard',
            [
                'stats' => [
                    'total' => $totalBookings,
                    'pending' => $pendingBookings,
                    'confirmed' => $confirmedBookings,
                    'cancelled' => $cancelledBookings,
                    'completed' => $completedBookings,
                ],

                'upcomingBookings' => $upcomingBookings,

                'nextBooking' => $nextBooking,
            ]
        );
    }
}