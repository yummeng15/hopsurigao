<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\BookingCancelledNotification;
use App\Services\PayMongoCheckoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;

class BookingCancellationController extends Controller
{
    public function __construct(
        private PayMongoCheckoutService $paymongo
    ) {}

    public function cancel(
        Request $request,
        Booking $booking
    ): RedirectResponse {
        abort_unless(
            (int) $booking->user_id ===
                (int) $request->user()->id,
            403
        );

        if ($booking->booking_status !== 'pending') {
            return $this->blocked(
                'Only pending reservations can be cancelled.'
            );
        }

        $payments = $booking->payments()->get();

        $pending = $payments->first(
            fn (Payment $payment): bool =>
                $payment->gateway === 'paymongo' &&
                $payment->payment_status === 'pending'
        );

        /*
         * Only a verified expired PayMongo session
         * can safely release a booking.
         */
        $expiredId = null;

        if ($pending !== null) {
            $expiredId = (string)
                $pending->gateway_checkout_id;

            $outcome = $this->paymongo
                ->expireUnpaidSession(
                    $booking,
                    $expiredId
                );

            if ($outcome === 'paid') {
                return $this->blocked(
                    'PayMongo reported a payment. Check your booking status; cancellation was not completed.'
                );
            }

            if ($outcome !== 'expired') {
                return $this->blocked(
                    'Checkout is still active, processing, or could not be verified. Try again later; your booking is unchanged.'
                );
            }
        }

        /*
         * Re-check the booking and payments
         * inside a database transaction.
         */
        $cancelled = DB::transaction(
            function () use (
                $booking,
                $request,
                $expiredId
            ): bool {
                $locked = Booking::query()
                    ->whereKey($booking->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                abort_unless(
                    (int) $locked->user_id ===
                        (int) $request->user()->id,
                    403
                );

                if (
                    $locked->booking_status !== 'pending'
                ) {
                    return false;
                }

                $rows = $locked->payments()
                    ->lockForUpdate()
                    ->get();

                foreach ($rows as $row) {
                    if (
                        $expiredId !== null &&
                        $row->gateway_checkout_id === $expiredId &&
                        $row->gateway === 'paymongo' &&
                        $row->payment_status === 'pending'
                    ) {
                        continue;
                    }

                    if (
                        $row->gateway === 'paymongo' &&
                        $row->payment_status === 'cancelled' &&
                        str_starts_with(
                            (string) $row->notes,
                            'Verified PayMongo checkout expired'
                        )
                    ) {
                        continue;
                    }

                    /*
                     * Never cancel over:
                     * - A recorded payment
                     * - An unknown earlier payment
                     * - Another unexpired checkout
                     */
                    return false;
                }

                if ($expiredId !== null) {
                    $current = $rows->first(
                        fn (Payment $row): bool =>
                            $row->gateway_checkout_id === $expiredId &&
                            $row->payment_status === 'pending'
                    );

                    if ($current === null) {
                        return false;
                    }

                    $current->update([
                        'payment_status' => 'cancelled',
                        'notes' =>
                            'Verified PayMongo checkout expired without payment.',
                    ]);
                }

                $locked->update([
                    'booking_status' => 'cancelled',
                ]);

                return true;
            },
            3
        );

        if (! $cancelled) {
            return $this->blocked(
                'The booking or its payment status changed. Nothing was cancelled; please review it.'
            );
        }

        /*
         * Notify the correct Operator and
         * Super Admin after saving.
         */
        try {
            $booking->loadMissing(
                'tourDate.package'
            );

            $operatorId = $booking->tourDate
                ?->package
                ?->tour_operator_id;

            $recipients = User::query()
                ->where(
                    function ($query) use ($operatorId): void {
                        $query->whereHas(
                            'role',
                            fn ($roles) =>
                                $roles->where(
                                    'name',
                                    'Superadmin'
                                )
                        );

                        if ($operatorId !== null) {
                            $query->orWhere(
                                function ($admins) use ($operatorId): void {
                                    $admins
                                        ->where(
                                            'tour_operator_id',
                                            $operatorId
                                        )
                                        ->whereHas(
                                            'role',
                                            fn ($roles) =>
                                                $roles->where(
                                                    'name',
                                                    'Admin'
                                                )
                                        );
                                }
                            );
                        }
                    }
                )
                ->get();

            if ($recipients->isNotEmpty()) {
                Notification::send(
                    $recipients,
                    new BookingCancelledNotification(
                        $booking->fresh(),
                        'Tourist'
                    )
                );
            }
        } catch (\Throwable $exception) {
            report($exception);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' =>
                'Booking cancelled. Any previous expired checkout remains in payment history.',
        ]);

        return redirect()->route(
            'user.history'
        );
    }

    /*
     * Permanent booking deletion remains disabled.
     */
    public function destroy(
        Request $request,
        Booking $booking
    ): RedirectResponse {
        abort_unless(
            (int) $booking->user_id ===
                (int) $request->user()->id,
            403
        );

        return $this->blocked(
            'Permanent deletion is disabled. Use Archive in Booking History.',
            'user.history'
        );
    }

    private function blocked(
        string $message,
        string $route = 'user.bookings.index'
    ): RedirectResponse {
        Inertia::flash('toast', [
            'type' => 'error',
            'message' => $message,
        ]);

        return redirect()->route(
            $route
        );
    }
}
