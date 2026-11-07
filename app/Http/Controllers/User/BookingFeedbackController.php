<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreBookingFeedbackRequest;
use App\Models\Booking;
use App\Models\User;
use App\Notifications\BookingFeedbackSubmittedNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;

class BookingFeedbackController extends Controller
{
    public function index(): Response
    {
        $userId = auth()->id();

        $bookings = Booking::query()
            ->where('user_id', $userId)
            ->whereIn('booking_status', ['confirmed', 'completed'])
            ->whereHas('tourDate', fn ($query) => $query->whereDate('tour_date', '<=', Carbon::today()))
            ->whereHas('payments')
            ->with([
                'feedback',
                'tourDate.package:id,package_name,destination,boat_name',
                'payments:id,booking_id,payment_status,payment_method',
            ])
            ->latest()
            ->get()
            ->map(fn (Booking $booking): array => [
                'id' => $booking->id,
                'booking_status' => $booking->booking_status,
                'package_name' => $booking->tourDate?->package?->package_name,
                'destination' => $booking->tourDate?->package?->destination,
                'boat_name' => $booking->tourDate?->package?->boat_name,
                'tour_date' => $booking->tourDate?->tour_date?->toDateString(),
                'has_feedback' => $booking->feedback !== null,
                'feedback' => $booking->feedback ? [
                    'enjoyment' => $booking->feedback->enjoyment,
                    'operator_service' => $booking->feedback->operator_service,
                    'overall_experience' => $booking->feedback->overall_experience,
                    'comment' => $booking->feedback->comment,
                ] : null,
            ])
            ->values();

        return Inertia::render('user/feedback/Index', [
            'bookings' => $bookings,
        ]);
    }

    public function create(Booking $booking): Response|RedirectResponse
    {
        abort_unless($booking->user_id === auth()->id(), 403);

        $booking->load([
            'feedback',
            'payments',
            'tourDate.package:id,package_name,destination',
        ]);

        if (! $this->isEligibleForFeedback($booking)) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'Feedback opens after your tour date for confirmed bookings with payment.',
            ]);

            return redirect()->route('user.feedback.index');
        }

        if ($booking->feedback) {
            return redirect()->route('user.feedback.index');
        }

        return Inertia::render('user/bookings/Feedback', [
            'booking' => [
                'id' => $booking->id,
                'package_name' => $booking->tourDate?->package?->package_name,
                'destination' => $booking->tourDate?->package?->destination,
                'tour_date' => $booking->tourDate?->tour_date?->format('M j, Y'),
            ],
        ]);
    }

    public function store(StoreBookingFeedbackRequest $request, Booking $booking): RedirectResponse
    {
        abort_unless($booking->user_id === $request->user()?->id, 403);

        $booking->load(['payments', 'tourDate', 'feedback']);

        abort_unless($this->isEligibleForFeedback($booking), 403);

        $isFirstFeedback = $booking->feedback === null;
        $comment = trim((string) $request->validated('comment'));

        $feedback = $booking->feedback()->updateOrCreate(
            ['booking_id' => $booking->id],
            [
                'user_id' => $request->user()->id,
                'enjoyment' => $request->validated('enjoyment'),
                'operator_service' => $request->validated('operator_service'),
                'overall_experience' => $request->validated('overall_experience'),
                'comment' => $comment === '' ? null : $comment,
            ],
        );

        if ($isFirstFeedback) {
            try {
                $booking->loadMissing('tourDate.package');
                $operatorId = $booking->tourDate?->package?->tour_operator_id;

                $recipients = User::query()
                    ->where(function ($query) use ($operatorId): void {
                        $query->whereHas('role', fn ($roles) => $roles->where('name', 'Superadmin'));

                        if ($operatorId !== null) {
                            $query->orWhere(function ($admins) use ($operatorId): void {
                                $admins->where('tour_operator_id', $operatorId)
                                    ->whereHas('role', fn ($roles) => $roles->where('name', 'Admin'));
                            });
                        }
                    })
                    ->get();

                if ($recipients->isNotEmpty()) {
                    Notification::send(
                        $recipients,
                        new BookingFeedbackSubmittedNotification($booking, $feedback)
                    );
                }
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Thanks for sharing your tour experience. The operator and administrator can see it.',
        ]);

        return redirect()->route('user.feedback.index');
    }

    private function isEligibleForFeedback(Booking $booking): bool
    {
        if (! in_array($booking->booking_status, ['confirmed', 'completed'], true)) {
            return false;
        }

        if ($booking->payments->isEmpty()) {
            return false;
        }

        $tourDate = $booking->tourDate?->tour_date;

        if ($tourDate === null) {
            return false;
        }

        return $tourDate->lessThanOrEqualTo(Carbon::today());
    }
}
