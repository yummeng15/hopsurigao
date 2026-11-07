<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BookingFeedback;
use Inertia\Inertia;
use Inertia\Response;

class BookingFeedbackController extends Controller
{
    public function index(): Response
    {
        $feedback = BookingFeedback::query()
            ->with([
                'user:id,name,email',
                'booking:id,user_id,tour_date_id,booking_status,number_of_guests',
                'booking.tourDate:id,package_id,tour_date',
                'booking.tourDate.package:id,package_name,boat_name',
            ])
            ->latest()
            ->get()
            ->map(function (BookingFeedback $item): array {
                $booking = $item->booking;
                $package = $booking?->tourDate?->package;

                return [
                    'id' => $item->id,
                    'booking_id' => $item->booking_id,
                    'guest_name' => $item->user?->name ?? 'Guest',
                    'guest_email' => $item->user?->email,
                    'package_name' => $package?->package_name ?? 'Tour',
                    'boat_name' => $package?->boat_name,
                    'tour_date' => $booking?->tourDate?->tour_date?->format('M j, Y'),
                    'booking_status' => $booking?->booking_status,
                    'enjoyment' => $item->enjoyment,
                    'operator_service' => $item->operator_service,
                    'overall_experience' => $item->overall_experience,
                    'comment' => $item->comment,
                    'submitted_at' => $item->created_at?->format('M j, Y g:i A'),
                ];
            })
            ->values();

        return Inertia::render('admin/feedback/Index', [
            'feedback' => $feedback,
            'summary' => [
                'total' => $feedback->count(),
                'enjoyed' => $feedback->where('enjoyment', 'enjoyed')->count(),
                'good_service' => $feedback->where('operator_service', 'good')->count(),
                'great' => $feedback->where('overall_experience', 'great')->count(),
                'poor' => $feedback->where('overall_experience', 'poor')->count(),
            ],
        ]);
    }
}
