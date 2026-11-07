<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Operator-only attendance and trip outcomes; never alters payments. */
class OperatorTripController extends Controller
{
    public function show(Request $request, int $booking): Response
    {
        $record = $this->ownedBooking($request, $booking)
            ->load(['user:id,name', 'tourDate.package', 'payments']);

        $trip = DB::table('booking_trip_records')
            ->where('booking_id', $record->id)
            ->first();

        // Only a successful QR scan makes this token available.
        // Opening the URL directly cannot automatically check in.
        $scanned = $request->session()->get('trip_scanned_token');
        $verifiedToken = is_string($scanned)
            && hash_equals((string) $record->qr_token, $scanned)
            ? $scanned : null;

        $tourDate = $record->tourDate?->tour_date?->toDateString();

        return Inertia::render('operator/TripManagement', [
            'booking' => [
                'id' => (int) $record->id,
                'guest' => (string) ($record->user?->name ?? 'Guest'),
                'package' => (string) ($record->tourDate?->package?->package_name ?? 'Tour'),
                'tour_date' => $tourDate,
                'booking_status' => (string) $record->booking_status,
                'guests' => (int) $record->number_of_guests,
                'amount_received' => $record->amountReceived(),
                'remaining_balance' => $record->remainingBalance(),
                'price_verified' => $record->price_snapshot_source === 'at_booking',
                'checked_in_at' => $trip?->checked_in_at,
                'outcome' => $trip?->outcome,
                'outcome_notes' => $trip?->notes,
                'recorded_at' => $trip?->recorded_at,
                'can_record_today' => $tourDate !== null && $tourDate <= today()->toDateString(),
                'can_check_in_today' => $tourDate === today()->toDateString(),
            ],
            'scannedToken' => $verifiedToken,
        ]);
    }

    public function checkIn(Request $request, int $booking): RedirectResponse
    {
        $data = $request->validate([
            'qr_token' => ['required', 'string', 'max:255'],
        ]);

        $operatorId = $this->operatorId($request);

        DB::transaction(function () use ($booking, $request, $data, $operatorId): void {
            $record = $this->lockedOwnedBooking($booking, $operatorId);

            $trip = DB::table('booking_trip_records')
                ->where('booking_id', $record->id)
                ->lockForUpdate()
                ->first();

            if (
                $record->booking_status !== 'confirmed'
                || $record->tourDate?->tour_date?->toDateString() !== today()->toDateString()
                || $trip?->outcome !== null
            ) {
                throw ValidationException::withMessages([
                    'qr_token' => 'Only a confirmed tour taking place today can be checked in.',
                ]);
            }

            if (! hash_equals((string) $record->qr_token, trim($data['qr_token']))) {
                throw ValidationException::withMessages([
                    'qr_token' => 'The scanned QR token does not match this booking.',
                ]);
            }

            if ($trip?->checked_in_at !== null) {
                return;
            }

            if ($trip === null) {
                DB::table('booking_trip_records')->insert([
                    'booking_id' => $record->id,
                    'checked_in_at' => now(),
                    'checked_in_by' => $request->user()->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('booking_trip_records')
                    ->where('booking_id', $record->id)
                    ->update([
                        'checked_in_at' => now(),
                        'checked_in_by' => $request->user()->id,
                        'updated_at' => now(),
                    ]);
            }
        }, 3);

        return redirect()->route('operator.trips.show', $booking)
            ->with('success', 'Passenger checked in.');
    }

    public function outcome(Request $request, int $booking): RedirectResponse
    {
        $data = $request->validate([
            'outcome' => [
                'required',
                Rule::in(['completed', 'passenger_no_show', 'operator_no_show']),
            ],
            'notes' => [
                'nullable',
                'string',
                'max:1000',
                'required_unless:outcome,completed',
                'min:10',
            ],
        ]);

        $operatorId = $this->operatorId($request);

        DB::transaction(function () use ($booking, $request, $data, $operatorId): void {
            $record = $this->lockedOwnedBooking($booking, $operatorId);

            $trip = DB::table('booking_trip_records')
                ->where('booking_id', $record->id)
                ->lockForUpdate()
                ->first();

            if (
                $record->booking_status !== 'confirmed'
                || $record->tourDate?->tour_date?->toDateString() > today()->toDateString()
                || $trip?->outcome !== null
            ) {
                throw ValidationException::withMessages([
                    'outcome' => 'This trip is not eligible, is in the future, or already has an outcome.',
                ]);
            }

            if ($data['outcome'] === 'completed') {
                if ($trip?->checked_in_at === null) {
                    throw ValidationException::withMessages([
                        'outcome' => 'Scan and check in the passenger before completing the trip.',
                    ]);
                }

                if (
                    $record->price_snapshot_source !== 'at_booking'
                    || $record->remainingBalance() > 0.009
                ) {
                    throw ValidationException::withMessages([
                        'outcome' => 'Verify the original price and collect the remaining balance before completing.',
                    ]);
                }

                $record->update(['booking_status' => 'completed']);
            }

            if (
                $data['outcome'] === 'passenger_no_show'
                && $trip?->checked_in_at !== null
            ) {
                throw ValidationException::withMessages([
                    'outcome' => 'A checked-in passenger cannot be marked as a no-show.',
                ]);
            }

            $values = [
                'outcome' => $data['outcome'],
                'notes' => $data['notes'] ?? null,
                'recorded_at' => now(),
                'recorded_by' => $request->user()->id,
                'updated_at' => now(),
            ];

            if ($trip === null) {
                DB::table('booking_trip_records')->insert($values + [
                    'booking_id' => $record->id,
                    'created_at' => now(),
                ]);
            } else {
                DB::table('booking_trip_records')
                    ->where('booking_id', $record->id)
                    ->update($values);
            }
        }, 3);

        return redirect()->route('operator.trips.show', $booking)
            ->with('success', 'Trip outcome recorded. Payments were not changed.');
    }

    private function operatorId(Request $request): int
    {
        $user = $request->user();

        abort_unless(
            $user?->role?->name === 'Admin'
                && $user->tourOperator?->canManageRecords() === true,
            403
        );

        return (int) $user->tour_operator_id;
    }

    private function ownedBooking(Request $request, int $booking): Booking
    {
        return Booking::query()
            ->whereHas(
                'tourDate.package',
                fn ($q) => $q->where('tour_operator_id', $this->operatorId($request))
            )
            ->whereKey($booking)
            ->firstOrFail();
    }

    private function lockedOwnedBooking(int $booking, int $operatorId): Booking
    {
        return Booking::query()
            ->whereHas(
                'tourDate.package',
                fn ($q) => $q->where('tour_operator_id', $operatorId)
            )
            ->whereKey($booking)
            ->lockForUpdate()
            ->with(['tourDate', 'payments'])
            ->firstOrFail();
    }
}
