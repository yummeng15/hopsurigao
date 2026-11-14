<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Package;
use App\Models\PickupLocation;
use App\Services\Reports\ReportManager;
use App\Support\CsvDownload;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OperatorReportController extends Controller
{
    public function __construct(
        protected ReportManager $reportManager
    ) {}

    public function index(Request $request): InertiaResponse
    {
        $operatorId = $this->operatorId($request);

        $packages = Package::query()
            ->where('tour_operator_id', $operatorId)
            ->select('id', 'package_name', 'destination', 'price')
            ->orderBy('package_name')
            ->get();

        $pickupLocations = PickupLocation::query()
            ->where('tour_operator_id', $operatorId)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        $nationalities = Booking::query()
            ->whereHas(
                'tourDate.package',
                fn ($query) => $query->where('tour_operator_id', $operatorId)
            )
            ->whereNotNull('nationality')
            ->where('nationality', '!=', '')
            ->distinct()
            ->orderBy('nationality')
            ->pluck('nationality')
            ->values();

        return Inertia::render('operator/reports/Index', [
            'reports' => $this->reportManager->getAvailableReportsForOperator(),
            'filterOptions' => [
                'packages' => $packages,
                'pickupLocations' => $pickupLocations,
                'nationalities' => $nationalities,
                'bookingStatuses' => ['pending', 'confirmed', 'completed', 'cancelled'],
                'paymentStatuses' => ['pending', 'partial', 'paid', 'confirmed', 'rejected', 'cancelled'],
                'paymentMethods' => ['gcash', 'cash'],
                'advisoryLevels' => ['green', 'yellow', 'orange', 'red'],
                'packageStatuses' => ['active', 'inactive'],
            ],
            'initialFilters' => [
                'type' => 'booking',
                'date_from' => Carbon::now()->startOfMonth()->format('Y-m-d'),
                'date_to' => Carbon::now()->endOfMonth()->format('Y-m-d'),
                'package_id' => 'all',
                'booking_status' => 'all',
                'payment_status' => 'all',
                'payment_method' => 'all',
                'pickup_location_id' => 'all',
                'nationality' => 'all',
                'advisory_level' => 'all',
                'status' => 'all',
            ],
            'endpoints' => [
                'preview' => route('operator.operations.reports.preview'),
                'download' => route('operator.operations.reports.download'),
                'export' => route('operator.operations.reports.export'),
            ],
            'operatorName' => (string) $request->user()->tourOperator->name,
        ]);
    }

    public function preview(Request $request): JsonResponse
    {
        $operatorId = $this->operatorId($request);
        $validated = $this->validatedFilters($request, $operatorId);

        try {
            $data = $this->reportManager->getData($validated['type'], $validated);

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function download(Request $request)
    {
        $operatorId = $this->operatorId($request);
        $validated = $this->validatedFilters($request, $operatorId);
        $generatedBy = auth()->user()?->name ?? 'Operator';

        $pdf = $this->reportManager->generatePdf($validated['type'], $validated, $generatedBy);
        $filename = $this->reportManager->getFilename($validated['type'], $validated);

        return $pdf->download($filename);
    }

    public function export(Request $request): StreamedResponse
    {
        $operatorId = $this->operatorId($request);
        $validated = $this->validatedFilters($request, $operatorId);

        $data = $this->reportManager->getData($validated['type'], $validated);
        $filename = $validated['type'].'-report-'.now()->format('Y-m-d').'.csv';

        return CsvDownload::fromRecords($filename, $data['records'] ?? []);
    }

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

    /**
     * @return array<string, mixed>
     */
    private function validatedFilters(Request $request, int $operatorId): array
    {
        $validated = $request->validate([
            'type' => ['required', 'string', Rule::in(ReportManager::OPERATOR_REPORT_IDS)],
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'package_id' => 'nullable',
            'booking_status' => 'nullable|string',
            'payment_status' => 'nullable|string',
            'payment_method' => 'nullable|string',
            'pickup_location_id' => 'nullable',
            'nationality' => 'nullable|string',
            'advisory_level' => 'nullable|string',
            'status' => 'nullable|string',
        ]);

        if (! empty($validated['package_id']) && $validated['package_id'] !== 'all') {
            abort_unless(
                Package::query()
                    ->where('id', $validated['package_id'])
                    ->where('tour_operator_id', $operatorId)
                    ->exists(),
                403,
                'Package is outside your authorized tour operator.'
            );
        }

        if (! empty($validated['pickup_location_id']) && $validated['pickup_location_id'] !== 'all') {
            abort_unless(
                PickupLocation::query()
                    ->where('id', $validated['pickup_location_id'])
                    ->where('tour_operator_id', $operatorId)
                    ->exists(),
                403,
                'Pickup location is outside your authorized tour operator.'
            );
        }

        $validated['tour_operator_id'] = $operatorId;

        return $validated;
    }
}
