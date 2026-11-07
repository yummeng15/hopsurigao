<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\FileUploadService;
use App\Services\UploadPath;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ArchivedBookingController extends Controller
{
    public function __construct(
        private FileUploadService $files
    ) {}

    /**
     * Display archived bookings page.
     */
    public function index(): Response
    {
        return Inertia::render(
            'admin/archived-bookings/Index'
        );
    }

    /**
     * DataTable JSON endpoint for archived bookings.
     */
    public function getData(Request $request): JsonResponse
    {
        $query = Booking::query()
            ->where('is_archived', true)
            ->with([
                'user',
                'tourDate.package',
                'pickupSchedule.pickupLocation',
                'payments',
            ]);

        /*
        |--------------------------------------------------------------------------
        | Search
        |--------------------------------------------------------------------------
        */

        if ($search = $request->input('search')) {
            $query->where(function ($query) use ($search) {
                $query
                    ->where('id', 'like', "%{$search}%")
                    ->orWhere('phone_number', 'like', "%{$search}%")
                    ->orWhere('nationality', 'like', "%{$search}%")
                    ->orWhere('booking_status', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($query) use ($search) {
                        $query->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })
                    ->orWhereHas('tourDate.package', function ($query) use ($search) {
                        $query->where('package_name', 'like', "%{$search}%")
                            ->orWhere('destination', 'like', "%{$search}%");
                    });
            });
        }

        /*
        |--------------------------------------------------------------------------
        | Filter Status
        |--------------------------------------------------------------------------
        */

        if ($status = $request->input('status')) {
            if ($status !== 'all') {
                $query->where('booking_status', $status);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Sort
        |--------------------------------------------------------------------------
        */

        $sortField = $request->input('sort', 'archived_at');
        $sortDirection = $request->input('direction', 'desc');

        $allowedSorts = [
            'id',
            'number_of_guests',
            'booking_status',
            'archived_at',
            'created_at',
        ];

        if (in_array($sortField, $allowedSorts, true)) {
            $query->orderBy($sortField, $sortDirection === 'asc' ? 'asc' : 'desc');
        } else {
            $query->latest('archived_at');
        }

        /*
        |--------------------------------------------------------------------------
        | Pagination
        |--------------------------------------------------------------------------
        */

        $perPage = $request->integer('per_page', 10);
        $bookings = $query->paginate($perPage);

        return response()->json($bookings);
    }

    /**
     * Permanently delete an archived booking.
     */
    public function destroy(Booking $booking): RedirectResponse
    {
        abort_unless($booking->is_archived, 400, 'Only archived bookings can be deleted from here.');

        if ($booking->booking_status === 'completed') {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'Completed bookings cannot be permanently deleted. Completed tour records are preserved for safety and audit logs.',
            ]);

            return redirect()->back();
        }

        // Delete uploaded files
        $this->files->deleteDirectory(
            UploadPath::payment($booking->id)
        );

        $bookingId = $booking->id;
        $booking->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Archived Booking #{$bookingId} has been permanently deleted.",
        ]);

        return redirect()->route('admin.archived-bookings.index');
    }

    /**
     * Bulk permanently delete archived bookings.
     */
    public function bulkDestroy(Request $request): RedirectResponse
    {
        $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'exists:bookings,id'],
        ]);

        $ids = $request->input('ids', []);
        $bookings = Booking::whereIn('id', $ids)
            ->where('is_archived', true)
            ->where('booking_status', '!=', 'completed')
            ->get();

        foreach ($bookings as $booking) {
            $this->files->deleteDirectory(
                UploadPath::payment($booking->id)
            );
            $booking->delete();
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => count($bookings) . ' eligible archived booking(s) permanently deleted.',
        ]);

        return redirect()->route('admin.archived-bookings.index');
    }
}

