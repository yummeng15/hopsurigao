<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingFeedback;
use App\Models\Package;
use App\Models\PickupLocation;
use App\Models\TourDate;
use App\Services\BookingPaymentStatus;
use App\Services\WeatherService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OperatorOperationsController extends Controller
{
    private function operatorId(Request $request): int
    {
        $user = $request->user();

        abort_unless(
            $user?->role?->name === 'Admin',
            403
        );

        abort_unless(
            $user->tourOperator?->is_active === true,
            403,
            'No active operator assigned.'
        );

        return (int) $user->tourOperator->id;
    }

    public function index(): RedirectResponse
    {
        return redirect()->route('operator.operations.boats');
    }

    public function boats(Request $request): Response
    {
        return $this->section($request, 'boats');
    }

    public function pickups(Request $request): Response
    {
        return $this->section($request, 'pickups');
    }

    public function weather(Request $request): Response
    {
        return $this->section($request, 'weather');
    }

    public function feedback(Request $request): Response
    {
        return $this->section($request, 'feedback');
    }

    public function reports(Request $request): Response
    {
        return $this->section($request, 'reports');
    }

    /**
     * @param  'boats'|'pickups'|'weather'|'feedback'|'reports'  $section
     */
    private function section(Request $request, string $section): Response
    {
        $id = $this->operatorId($request);

        $payload = [
            'section' => $section,
            'operatorName' => $request->user()->tourOperator->name,
            'boats' => [],
            'locations' => [],
            'dates' => [],
            'feedback' => [],
            'summary' => [
                'pending' => 0,
                'confirmed' => 0,
                'completed' => 0,
                'cancelled' => 0,
            ],
            'packageReport' => [],
            'operatorReport' => null,
        ];

        if ($section === 'boats') {
            $payload['boats'] = Package::query()
                ->where('tour_operator_id', $id)
                ->orderBy('boat_name')
                ->get()
                ->map(fn (Package $p): array => [
                    'id' => $p->id,
                    'package_name' => $p->package_name,
                    'boat_name' => $p->boat_name ?? '',
                    'under_maintenance' => (bool) $p->under_maintenance,
                ])->values();
        }

        if ($section === 'pickups') {
            $payload['locations'] = PickupLocation::query()
                ->where(function ($q) use ($id): void {
                    $q->where('tour_operator_id', $id)
                        ->orWhereNull('tour_operator_id');
                })
                ->orderBy('name')
                ->get()
                ->map(fn (PickupLocation $p): array => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'address' => $p->address,
                    'latitude' => $p->latitude === null
                        ? null : (float) $p->latitude,
                    'longitude' => $p->longitude === null
                        ? null : (float) $p->longitude,
                    'status' => $p->status,
                    'is_shared' => $p->tour_operator_id === null,
                ])->values();
        }

        if ($section === 'weather') {
            $payload['dates'] = TourDate::query()
                ->whereHas(
                    'package',
                    fn ($q) => $q->where('tour_operator_id', $id)
                )
                ->whereBetween('tour_date', [
                    today()->toDateString(),
                    today()->addDays(30)->toDateString(),
                ])
                ->with([
                    'package:id,package_name,tour_operator_id',
                    'weatherForecast',
                ])
                ->orderBy('tour_date')
                ->limit(100)
                ->get()
                ->map(fn (TourDate $d): array => [
                    'id' => $d->id,
                    'date' => $d->tour_date?->format('Y-m-d'),
                    'package_name' => $d->package?->package_name ?? 'Unknown package',
                    'travel_closed' => (bool) $d->travel_closed,
                    'advisory_level' => $d->weatherForecast?->advisory_level ?? 'unavailable',
                    'advisory_badge' => $d->weatherForecast?->advisory_badge ?? 'Not yet fetched',
                    'forecast_updated' => $d->weatherForecast?->fetched_at
                        ?->format('M j, Y g:i A'),
                ])->values();
        }

        if ($section === 'feedback') {
            $payload['feedback'] = BookingFeedback::query()
                ->whereHas(
                    'booking.tourDate.package',
                    fn ($q) => $q->where('tour_operator_id', $id)
                )
                ->with([
                    'user:id,name',
                    'booking.tourDate.package:id,package_name,tour_operator_id',
                ])
                ->latest()
                ->limit(50)
                ->get()
                ->map(fn (BookingFeedback $f): array => [
                    'id' => $f->id,
                    'booking_id' => $f->booking_id,
                    'guest_name' => $f->user?->name ?? 'Guest',
                    'package_name' => $f->booking?->tourDate?->package?->package_name ?? 'Tour',
                    'enjoyment' => $f->enjoyment,
                    'operator_service' => $f->operator_service,
                    'overall_experience' => $f->overall_experience,
                    'comment' => $f->comment,
                ])->values();
        }

        if ($section === 'reports') {
            $bookings = Booking::query()
                ->whereHas(
                    'tourDate.package',
                    fn ($q) => $q->where('tour_operator_id', $id)
                )
                ->with([
                    'tourDate.package:id,package_name,tour_operator_id',
                    'payments',
                ])
                ->get();

            $counts = $bookings
                ->groupBy('booking_status')
                ->map->count();

            $payload['summary'] = [
                'pending' => (int) ($counts['pending'] ?? 0),
                'confirmed' => (int) ($counts['confirmed'] ?? 0),
                'completed' => (int) ($counts['completed'] ?? 0),
                'cancelled' => (int) ($counts['cancelled'] ?? 0),
            ];

            $byPackage = [];

            foreach ($bookings as $booking) {
                $package = $booking->tourDate?->package;
                $packageId = (int) ($package?->id ?? 0);
                $packageName = (string) ($package?->package_name ?? 'Unknown package');

                if (! isset($byPackage[$packageId])) {
                    $byPackage[$packageId] = [
                        'package_id' => $packageId,
                        'package_name' => $packageName,
                        'operator_name' => (string) $request->user()->tourOperator->name,
                        'total_bookings' => 0,
                        'pending_bookings' => 0,
                        'confirmed_bookings' => 0,
                        'completed_bookings' => 0,
                        'cancelled_bookings' => 0,
                        'revenue' => 0.0,
                        'gcash_revenue' => 0.0,
                        'cash_revenue' => 0.0,
                    ];
                }

                $byPackage[$packageId]['total_bookings']++;

                match ($booking->booking_status) {
                    'pending' => $byPackage[$packageId]['pending_bookings']++,
                    'confirmed' => $byPackage[$packageId]['confirmed_bookings']++,
                    'completed' => $byPackage[$packageId]['completed_bookings']++,
                    'cancelled' => $byPackage[$packageId]['cancelled_bookings']++,
                    default => null,
                };

                foreach ($booking->payments as $payment) {
                    if (! in_array($payment->payment_status, ['paid', 'partial'], true)) {
                        continue;
                    }

                    $amount = (float) $payment->amount;
                    $method = strtolower((string) $payment->payment_method);
                    $gateway = strtolower((string) $payment->gateway);

                    if ($gateway === 'paymongo' || $method === 'gcash') {
                        $byPackage[$packageId]['gcash_revenue'] += $amount;
                    } else {
                        $byPackage[$packageId]['cash_revenue'] += $amount;
                    }

                    $byPackage[$packageId]['revenue'] += $amount;
                }
            }

            $packageReport = collect($byPackage)
                ->map(function (array $row): array {
                    $row['gcash_revenue'] = round($row['gcash_revenue'], 2);
                    $row['cash_revenue'] = round($row['cash_revenue'], 2);
                    $row['revenue'] = round($row['revenue'], 2);

                    return $row;
                })
                ->sortByDesc('total_bookings')
                ->values()
                ->all();

            $payload['packageReport'] = $packageReport;
            $payload['operatorReport'] = [
                'operator_name' => (string) $request->user()->tourOperator->name,
                'total_bookings' => $bookings->count(),
                'pending_bookings' => $bookings->where('booking_status', 'pending')->count(),
                'confirmed_bookings' => $bookings->where('booking_status', 'confirmed')->count(),
                'completed_bookings' => $bookings->where('booking_status', 'completed')->count(),
                'cancelled_bookings' => $bookings->where('booking_status', 'cancelled')->count(),
                'revenue' => round((float) collect($packageReport)->sum('revenue'), 2),
                'gcash_revenue' => round((float) collect($packageReport)->sum('gcash_revenue'), 2),
                'cash_revenue' => round((float) collect($packageReport)->sum('cash_revenue'), 2),
            ];
        }

        return Inertia::render('operator/Operations', $payload);
    }

    public function updateBoat(
        Request $request,
        int $package
    ): RedirectResponse {
        $id = $this->operatorId($request);

        $boat = Package::query()
            ->where('tour_operator_id', $id)
            ->findOrFail($package);

        $validated = $request->validate([
            'boat_name' => ['required', 'string', 'max:255'],
            'under_maintenance' => ['required', 'boolean'],
        ]);

        $boat->update([
            'boat_name' => trim($validated['boat_name']),
            'under_maintenance' => $validated['under_maintenance'],
        ]);

        return back()->with(
            'success',
            'Boat maintenance updated.'
        );
    }

    private function locationInput(
        Request $request,
        ?int $ignore = null
    ): array {
        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('pickup_locations', 'name')
                    ->ignore($ignore),
            ],
            'address' => ['nullable', 'string', 'max:500'],
            'latitude' => [
                'nullable',
                'required_with:longitude',
                'numeric',
                'between:-90,90',
            ],
            'longitude' => [
                'nullable',
                'required_with:latitude',
                'numeric',
                'between:-180,180',
            ],
            'status' => [
                'required',
                Rule::in(['active', 'inactive']),
            ],
        ]);
    }

    public function storeLocation(
        Request $request
    ): RedirectResponse {
        $id = $this->operatorId($request);
        $data = $this->locationInput($request);

        $location = new PickupLocation($data);

        // Ownership is also enforced by the model.
        $location->tour_operator_id = $id;
        $location->save();

        return back()->with(
            'success',
            'Pickup location created.'
        );
    }

    public function updateLocation(
        Request $request,
        int $location
    ): RedirectResponse {
        $id = $this->operatorId($request);

        $record = PickupLocation::query()
            ->where('tour_operator_id', $id)
            ->findOrFail($location);

        $record->update(
            $this->locationInput($request, $record->id)
        );

        return back()->with(
            'success',
            'Pickup location updated.'
        );
    }

    public function destroyLocation(
        Request $request,
        int $location
    ): RedirectResponse {
        $id = $this->operatorId($request);

        $record = PickupLocation::query()
            ->where('tour_operator_id', $id)
            ->findOrFail($location);

        if (
            $record->pickupSchedules()
                ->withoutGlobalScopes()
                ->exists()
        ) {
            return back()->withErrors([
                'location' => 'This pickup location is used by a tour schedule and cannot be deleted.',
            ]);
        }

        $record->delete();

        return back()->with(
            'success',
            'Pickup location deleted.'
        );
    }

    private function ownedDate(
        Request $request,
        int $tourDate
    ): TourDate {
        $id = $this->operatorId($request);

        return TourDate::query()
            ->whereHas(
                'package',
                fn ($q) => $q->where('tour_operator_id', $id)
            )
            ->findOrFail($tourDate);
    }

    public function toggleTravel(
        Request $request,
        int $tourDate
    ): RedirectResponse {
        $date = $this->ownedDate($request, $tourDate);

        $date->update([
            'travel_closed' => ! $date->travel_closed,
        ]);

        return back()->with(
            'success',
            $date->travel_closed
                ? 'Tour date closed for new bookings.'
                : 'Manual closure removed. Dangerous weather can still block bookings.'
        );
    }

    public function refreshWeather(
        Request $request,
        int $tourDate,
        WeatherService $weather
    ): RedirectResponse {
        $date = $this->ownedDate($request, $tourDate);

        $forecast = $weather->getForecastForTourDate(
            $date,
            true
        );

        return $forecast
            ? back()->with('success', 'Weather forecast refreshed.')
            : back()->withErrors([
                'weather' => 'Weather service unavailable. Existing forecast retained.',
            ]);
    }

    public function report(
        Request $request,
        BookingPaymentStatus $paymentStatus,
    ): StreamedResponse {
        $id = $this->operatorId($request);

        $values = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:from',
            ],
        ]);

        $start = Carbon::parse(
            $values['from']
        )->startOfDay();

        $end = Carbon::parse(
            $values['to']
        )->endOfDay();

        abort_if(
            $start->diffInDays($end) > 366,
            422,
            'Maximum report period is 366 days.'
        );

        $filename = 'operator-bookings-'
            .$values['from'].'-'.$values['to'].'.csv';

        return response()->streamDownload(
            function () use ($id, $start, $end, $paymentStatus): void {
                $out = fopen('php://output', 'w');

                fputcsv($out, [
                    'Booking ID',
                    'Booked At',
                    'Tourist',
                    'Package',
                    'Tour Date',
                    'Guests',
                    'Booking Status',
                    'Payment Status',
                    'Price Source',
                    'Saved Total',
                    'Amount Received',
                    'Balance',
                    'GCash Received',
                    'Cash Received',
                ]);

                Booking::query()
                    ->whereHas(
                        'tourDate.package',
                        fn ($q) => $q->where(
                            'tour_operator_id',
                            $id
                        )
                    )
                    ->whereBetween('created_at', [
                        $start,
                        $end,
                    ])
                    ->with([
                        'user:id,name',
                        'tourDate.package',
                        'payments',
                    ])
                    ->orderBy('id')
                    ->chunkById(
                        200,
                        function ($bookings) use ($out, $paymentStatus): void {
                            foreach ($bookings as $b) {
                                $summary = $paymentStatus->summarize($b);
                                $gcash = 0.0;
                                $cash = 0.0;

                                foreach ($b->payments as $payment) {
                                    if (! in_array($payment->payment_status, ['paid', 'partial'], true)) {
                                        continue;
                                    }

                                    $amount = (float) $payment->amount;
                                    $method = strtolower((string) $payment->payment_method);
                                    $gateway = strtolower((string) $payment->gateway);

                                    if ($gateway === 'paymongo' || $method === 'gcash') {
                                        $gcash += $amount;
                                    } else {
                                        $cash += $amount;
                                    }
                                }

                                // Prevent spreadsheet formula injection.
                                $text = fn ($v): string => preg_match(
                                    '/^[=+\-@\t\r]/u',
                                    (string) $v
                                )
                                        ? "'".(string) $v
                                        : (string) $v;

                                fputcsv($out, [
                                    $b->id,
                                    $b->created_at?->format('Y-m-d H:i'),
                                    $text($b->user?->name ?? ''),
                                    $text(
                                        $b->tourDate?->package?->package_name
                                        ?? ''
                                    ),
                                    $b->tourDate?->tour_date
                                        ?->format('Y-m-d'),
                                    $b->number_of_guests,
                                    $b->booking_status,
                                    $summary['label'],
                                    $b->price_snapshot_source,
                                    $summary['total_amount'],
                                    $summary['amount_paid'],
                                    $summary['remaining_balance'],
                                    round($gcash, 2),
                                    round($cash, 2),
                                ]);
                            }
                        }
                    );

                fclose($out);
            },
            $filename,
            [
                'Content-Type' => 'text/csv; charset=UTF-8',
            ]
        );
    }
}
