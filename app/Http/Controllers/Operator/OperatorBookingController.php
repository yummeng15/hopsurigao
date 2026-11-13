<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\BookingPaymentStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Operator-only read endpoints. No booking, QR or payment mutations here.
 * Tenant IDs are always obtained from the signed-in account.
 */
class OperatorBookingController extends Controller
{
    public function __construct(
        private BookingPaymentStatus $paymentStatus,
    ) {}

    public function index(Request $request): Response
    {
        $operatorId = $this->operatorId($request);

        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['pending', 'confirmed', 'completed', 'cancelled'])],
            'search' => ['nullable', 'string', 'max:100'],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $status = $filters['status'] ?? '';
        $search = trim($filters['search'] ?? '');
        $date = $filters['date'] ?? '';

        $query = $this->ownedBookings($operatorId)
            ->with([
                'user:id,name',
                'tourDate:id,package_id,tour_date',
                'tourDate.package:id,package_name',
                'payments',
            ]);

        if ($status !== '') {
            $query->where('booking_status', $status);
        }

        if ($search !== '') {
            $query->whereHas('user', fn (Builder $users) => $users->where('name', 'like', '%'.$search.'%'));
        }

        if ($date !== '') {
            $query->whereHas('tourDate', fn (Builder $dates) => $dates->whereDate('tour_date', $date));
        }

        $bookings = $query->orderByDesc('id')
            ->paginate(12)
            ->withQueryString()
            ->through(function (Booking $booking): array {
                $payment = $this->paymentStatus->summarize($booking);

                return [
                    'id' => (int) $booking->id,
                    'tourist' => (string) ($booking->user?->name ?? 'Unknown customer'),
                    'package' => (string) ($booking->tourDate?->package?->package_name ?? 'Unknown tour'),
                    'tour_date' => $booking->tourDate?->tour_date?->format('Y-m-d'),
                    'guests' => (int) $booking->number_of_guests,
                    'status' => (string) $booking->booking_status,
                    'payment_status' => $payment['status'],
                    'payment_label' => $payment['label'],
                    'amount_paid' => $payment['amount_paid'],
                    'total_amount' => $payment['total_amount'],
                ];
            });

        return Inertia::render('operator/Bookings', [
            'bookings' => $bookings,
            'filters' => [
                'status' => $status,
                'search' => $search,
                'date' => $date,
            ],
        ]);
    }

    public function show(Request $request, int $booking): Response
    {
        $operatorId = $this->operatorId($request);

        $record = $this->ownedBookings($operatorId)
            ->whereKey($booking)
            ->with([
                'user:id,name,email',
                'tourDate:id,package_id,tour_date',
                'tourDate.package:id,package_name,destination',
                'pickupSchedule:id,pickup_location_id,pickup_time',
                'pickupSchedule.pickupLocation:id,name',
                'payments',
            ])
            ->firstOrFail();

        $payment = $this->paymentStatus->summarize($record);

        return Inertia::render('operator/BookingDetails', [
            'booking' => [
                'id' => (int) $record->id,
                'customer_name' => (string) ($record->user?->name ?? 'Unknown customer'),
                'customer_email' => (string) ($record->user?->email ?? ''),
                'contact_number' => (string) ($record->phone_number ?? ''),
                'nationality' => (string) ($record->nationality ?? ''),
                'special_request' => (string) ($record->special_request ?? ''),
                'package' => (string) ($record->tourDate?->package?->package_name ?? 'Unknown tour'),
                'destination' => (string) ($record->tourDate?->package?->destination ?? ''),
                'tour_date' => $record->tourDate?->tour_date?->format('Y-m-d'),
                'pickup_location' => (string) ($record->pickupSchedule?->pickupLocation?->name ?? 'Not provided'),
                'pickup_time' => $record->pickupSchedule
                    ? substr((string) $record->pickupSchedule->getRawOriginal('pickup_time'), 0, 5)
                    : null,
                'guests' => (int) $record->number_of_guests,
                'status' => (string) $record->booking_status,
                'created_at' => $record->created_at?->format('Y-m-d H:i'),
                'payment' => $payment,
            ],
        ]);
    }

    public function calendar(Request $request): Response
    {
        $operatorId = $this->operatorId($request);

        $month = $request->query('month', now()->format('Y-m'));
        if (! is_string($month) || ! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D', $month)) {
            abort(422, 'Enter a valid calendar month (YYYY-MM).');
        }

        $start = CarbonImmutable::createFromFormat('!Y-m', $month)->startOfMonth();
        $end = $start->endOfMonth();

        // Use explicit package ownership in this aggregate query; DB::table
        // does not apply Eloquent global scopes.
        $days = DB::table('bookings as bookings')
            ->join('tour_dates as tour_dates', 'tour_dates.id', '=', 'bookings.tour_date_id')
            ->join('packages as packages', 'packages.id', '=', 'tour_dates.package_id')
            ->where('packages.tour_operator_id', $operatorId)
            ->whereBetween('tour_dates.tour_date', [$start->toDateString(), $end->toDateString()])
            ->whereIn('bookings.booking_status', ['pending', 'confirmed', 'completed'])
            ->selectRaw('tour_dates.tour_date as date, COUNT(*) as bookings, COALESCE(SUM(bookings.number_of_guests), 0) as guests')
            ->groupBy('tour_dates.tour_date')
            ->orderBy('tour_dates.tour_date')
            ->get()
            ->map(fn ($day): array => [
                'date' => (string) $day->date,
                'bookings' => (int) $day->bookings,
                'guests' => (int) $day->guests,
            ])->all();

        return Inertia::render('operator/BookingCalendar', [
            'month' => $start->format('Y-m'),
            'monthLabel' => $start->format('F Y'),
            'previousMonth' => $start->subMonth()->format('Y-m'),
            'nextMonth' => $start->addMonth()->format('Y-m'),
            'days' => $days,
        ]);
    }

    private function operatorId(Request $request): int
    {
        $user = $request->user();

        abort_unless($user?->role?->name === 'Admin', 403);
        abort_unless($user->tourOperator?->canManageRecords() === true, 403);

        return (int) $user->tour_operator_id;
    }

    /** @return Builder<Booking> */
    private function ownedBookings(int $operatorId): Builder
    {
        return Booking::query()->whereHas('tourDate', fn (Builder $date) => $date->whereHas('package', fn (Builder $package) => $package->where('tour_operator_id', $operatorId)));
    }
}
