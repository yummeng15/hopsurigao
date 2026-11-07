<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Verifies an Operator-owned booking QR.
 * Successful scans lead to an explicit check-in confirmation.
 */
class OperatorQrController extends Controller
{
    public function index(Request $request): Response
    {
        $this->operatorId($request);

        return Inertia::render('operator/QrScanner');
    }

    public function verify(Request $request): RedirectResponse
    {
        $operatorId = $this->operatorId($request);

        $validated = $request->validate([
            'qr_token' => ['required', 'string', 'max:255'],
        ]);

        $booking = $this->ownedBookings($operatorId)
            ->where('qr_token', trim($validated['qr_token']))
            ->first();

        if ($booking === null) {
            return back()->withErrors([
                'qr_token' => 'QR token is invalid or not assigned to your tour business.',
            ]);
        }

        // A successfully scanned token is flashed for one redirect only.
        // Check-in still requires a separate explicit confirmation.
        return redirect()->route('operator.trips.show', $booking->id)
            ->with('trip_scanned_token', trim($validated['qr_token']));
    }

    public function show(
        Request $request,
        int $booking
    ): Response {
        $operatorId = $this->operatorId($request);

        $record = $this->ownedBookings($operatorId)
            ->whereKey($booking)
            ->with([
                'user:id,name',
                'tourDate.package:id,package_name,price',
                'payments',
            ])
            ->firstOrFail();

        return Inertia::render('operator/QrVerified', [
            'booking' => [
                'id' => (int) $record->id,
                'customer_name' => (string) ($record->user?->name ?? 'Unknown customer'),
                'tour_name' => (string) ($record->tourDate?->package?->package_name
                        ?? 'Unknown tour'),
                'tour_date' => $record->tourDate?->tour_date?->format('Y-m-d'),
                'guests' => (int) $record->number_of_guests,
                'booking_status' => (string) $record->booking_status,
                'amount_received' => $record->amountReceived(),
                'remaining_balance' => $record->remainingBalance(),
            ],
        ]);
    }

    private function operatorId(Request $request): int
    {
        $user = $request->user();

        abort_unless($user?->role?->name === 'Admin', 403);

        abort_unless(
            $user->tourOperator?->canManageRecords() === true,
            403
        );

        return (int) $user->tour_operator_id;
    }

    private function ownedBookings(
        int $operatorId
    ): Builder {
        return Booking::query()
            ->whereHas(
                'tourDate.package',
                fn ($packages) => $packages->where('tour_operator_id', $operatorId)
            );
    }
}
