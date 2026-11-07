<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\PayMongoCheckoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class PayMongoPaymentController extends Controller
{
    public function __construct(
        private PayMongoCheckoutService $paymongo
    ) {}

    /**
     * Send the tourist to PayMongo's hosted payment page.
     */
    public function checkout(Request $request, Booking $booking): Response|RedirectResponse
    {
        $this->authorizeBooking($request, $booking);

        abort_unless($booking->booking_status === 'pending', 403);

        $validated = $request->validate([
            'payment_method' => ['required', 'in:gcash'],
            'payment_coverage' => ['required', 'in:full'],
            'accepted_policy' => ['accepted'],
        ]);

        $result = $this->paymongo->createCheckout(
            $booking,
            $validated['payment_method'],
            'full',
        );

        if (! $result['ok']) {
            return back()->withErrors([
                'payment_method' => $result['message'] ?? 'PayMongo could not start the payment.',
            ]);
        }

        return Inertia::location($result['url']);
    }

    /**
     * PayMongo sends the browser here after the hosted page.
     * The booking is confirmed only when PayMongo reports the same amount as paid.
     */
    public function returned(Request $request, Booking $booking): RedirectResponse
    {
        $this->authorizeBooking($request, $booking);

        if ($this->paymongo->settleStoredCheckout($booking)) {
            Inertia::flash('toast', [
                'type' => 'success',
                'message' => 'Payment received. Your booking is confirmed. After your tour, leave feedback from Feedback in the menu.',
            ]);

            return redirect()->route('user.bookings.show', $booking);
        }

        Inertia::flash('toast', [
            'type' => 'error',
            'message' => 'PayMongo has not confirmed the payment yet. If you already paid, wait a moment and open this booking again. Do not upload a screenshot.',
        ]);

        return redirect()->route('user.bookings.payment', $booking);
    }

    private function authorizeBooking(Request $request, Booking $booking): void
    {
        abort_unless($booking->user_id === $request->user()?->id, 403);
    }
}
