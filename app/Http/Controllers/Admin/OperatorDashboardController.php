<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Package;
use App\Models\TourDate;
use Inertia\Inertia;
use Inertia\Response;

class OperatorDashboardController extends Controller
{
    public function index(): Response
    {
        $operator = auth()->user()?->tourOperator;

        // Defense in depth: the role middleware also requires an active
        // assigned operator. Never show another company's data.
        abort_unless($operator?->canManageRecords(), 403, 'An approved tour operator assignment is required.');

        $operatorId = $operator->id;
        $today = now()->toDateString();

        $stats = [
            'today_tours' => TourDate::query()
                ->whereDate('tour_date', $today)
                ->whereHas('package', fn ($query) => $query
                    ->where('tour_operator_id', $operatorId))
                ->count(),

            'today_bookings' => Booking::query()
                ->whereIn('booking_status', ['confirmed', 'completed'])
                ->whereHas('tourDate', fn ($query) => $query
                    ->whereDate('tour_date', $today)
                    ->whereHas('package', fn ($packages) => $packages
                        ->where('tour_operator_id', $operatorId)))
                ->count(),

            'pending_bookings' => Booking::query()
                ->where('booking_status', 'pending')
                ->whereHas('tourDate.package', fn ($query) => $query
                    ->where('tour_operator_id', $operatorId))
                ->count(),

            'boats_under_maintenance' => Package::query()
                ->where('tour_operator_id', $operatorId)
                ->where('under_maintenance', true)
                ->count(),
        ];

        $todayBookings = Booking::query()
            ->with([
                'user:id,name',
                'tourDate:id,package_id,tour_date',
                'tourDate.package:id,package_name',
            ])
            ->whereHas('tourDate', fn ($query) => $query
                ->whereDate('tour_date', $today)
                ->whereHas('package', fn ($packages) => $packages
                    ->where('tour_operator_id', $operatorId)))
            ->orderByDesc('id')
            ->limit(6)
            ->get()
            ->map(fn (Booking $booking) => [
                'id' => $booking->id,
                'tourist' => $booking->user?->name ?? 'Walk-in guest',
                'package' => $booking->tourDate?->package?->package_name ?? 'Unknown package',
                'guests' => (int) $booking->number_of_guests,
                'status' => $booking->booking_status,
            ])
            ->values();

        return Inertia::render('operator/Dashboard', [
            'stats' => $stats,
            'todayBookings' => $todayBookings,
        ]);
    }
}
