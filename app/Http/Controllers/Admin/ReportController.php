<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Package;
use App\Models\PickupLocation;
use App\Services\Reports\ReportManager;
use App\Support\CsvDownload;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(
        protected ReportManager $reportManager
    ) {}

    /**
     * Display the Reports dashboard and filter generator.
     */
    public function index(Request $request): InertiaResponse
    {
        $packages = Package::select('id', 'package_name', 'destination', 'price')
            ->orderBy('package_name')
            ->get();

        $pickupLocations = PickupLocation::select('id', 'name')
            ->orderBy('name')
            ->get();

        $nationalities = Booking::whereNotNull('nationality')
            ->where('nationality', '!=', '')
            ->distinct()
            ->orderBy('nationality')
            ->pluck('nationality')
            ->values();

        return Inertia::render('admin/reports/Index', [
            'reports' => $this->reportManager->getAvailableReports(),
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
        ]);
    }

    /**
     * Return live preview data and summary KPIs for on-screen inspection.
     */
    public function preview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|string',
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

    /**
     * Generate and stream/download the PDF report.
     */
    public function download(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|string',
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

        $type = $validated['type'];
        $generatedBy = auth()->user()?->name ?? 'System Operator';

        $pdf = $this->reportManager->generatePdf($type, $validated, $generatedBy);
        $filename = $this->reportManager->getFilename($type, $validated);

        return $pdf->download($filename);
    }

    /**
     * Download the current report filters as a spreadsheet.
     */
    public function export(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'type' => 'required|string',
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

        $data = $this->reportManager->getData($validated['type'], $validated);
        $filename = $validated['type'].'-report-'.now()->format('Y-m-d').'.csv';

        return CsvDownload::fromRecords($filename, $data['records'] ?? []);
    }
}
