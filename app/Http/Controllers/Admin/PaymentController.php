<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePaymentRequest;
use App\Http\Requests\Admin\UpdatePaymentRequest;
use App\Models\Payment;
use App\Services\FileUploadService;
use App\Services\UploadPath;
use App\Support\CsvDownload;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentController extends Controller
{
    public function __construct(
        private FileUploadService $files
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Index
    |--------------------------------------------------------------------------
    */

    public function index(): Response
    {
        return Inertia::render('admin/payments/Index');
    }

    /*
    |--------------------------------------------------------------------------
    | DataTable
    |--------------------------------------------------------------------------
    */

    public function getData(Request $request): JsonResponse
    {
        $query = Payment::query()
            ->with([
                'booking.user',
                'booking.tourDate.package',
                'booking.pickupSchedule.pickupLocation',
            ]);

        // Search
        if ($search = $request->input('search')) {
            $query->where(function ($query) use ($search) {
                $query
                    ->where('id', 'like', "%{$search}%")
                    ->orWhere('booking_id', 'like', "%{$search}%")
                    ->orWhere('transaction_reference', 'like', "%{$search}%")
                    ->orWhere('payment_method', 'like', "%{$search}%")
                    ->orWhere('payment_status', 'like', "%{$search}%")
                    ->orWhere('amount', 'like', "%{$search}%")
                    ->orWhereHas('booking.user', function ($query) use ($search) {
                        $query
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('phone_number', 'like', "%{$search}%");
                    })
                    ->orWhereHas('booking.tourDate.package', function ($query) use ($search) {
                        $query
                            ->where('package_name', 'like', "%{$search}%")
                            ->orWhere('destination', 'like', "%{$search}%");
                    });
            });
        }

        // Status Filter
        if ($status = $request->input('payment_status')) {
            $query->where('payment_status', $status);
        }

        // Sorting
        $allowedSorts = [
            'id',
            'booking_id',
            'amount',
            'payment_method',
            'payment_status',
            'transaction_reference',
            'paid_at',
            'created_at',
        ];

        $sort = $request->input('sort');

        if ($sort && in_array($sort, $allowedSorts, true)) {
            $order = $request->input('order', 'asc');

            if (! in_array($order, ['asc', 'desc'], true)) {
                $order = 'asc';
            }

            $query->orderBy($sort, $order);
        } else {
            $query->latest();
        }

        // Pagination
        $perPage = (int) $request->input('per_page', 10);
        $perPage = min(max($perPage, 1), 100);

        // Response
        return response()->json(
            $query
                ->paginate($perPage)
                ->through(function (Payment $payment) {
                    $proofFiles = $this->files->filesWithMeta(
                        UploadPath::payment($payment->booking_id)
                    );

                    $proof = $proofFiles[0] ?? null;

                    return [
                        'id' => $payment->id,
                        'booking_id' => $payment->booking_id,
                        'user_name' => $payment->booking?->user?->name ?? 'Guest User',
                        'user_email' => $payment->booking?->user?->email ?? ($payment->booking?->phone_number ?? 'N/A'),
                        'user_phone' => $payment->booking?->phone_number ?? '-',
                        'package_name' => $payment->booking?->tourDate?->package?->package_name ?? 'N/A',
                        'destination' => $payment->booking?->tourDate?->package?->destination ?? 'N/A',
                        'tour_date' => $payment->booking?->tourDate?->tour_date ? Carbon::parse($payment->booking->tourDate->tour_date)->toDateString() : '-',
                        'number_of_guests' => $payment->booking?->number_of_guests ?? 1,
                        'amount' => (float) $payment->amount,
                        'payment_method' => $payment->payment_method,
                        'payment_status' => $payment->payment_status,
                        'transaction_reference' => $payment->transaction_reference,
                        'proof_of_payment_url' => $proof['url'] ?? null,
                        'proof_of_payment_is_image' => $proof['is_image'] ?? null,
                        'proof_files' => $proofFiles,
                        'notes' => $payment->notes,
                        'paid_at' => $payment->paid_at?->format('Y-m-d H:i:s'),
                        'created_at' => $payment->created_at?->format('Y-m-d H:i:s'),
                        'booking' => $payment->booking ? [
                            'id' => $payment->booking->id,
                            'booking_status' => $payment->booking->booking_status,
                            'number_of_guests' => $payment->booking->number_of_guests,
                            'phone_number' => $payment->booking->phone_number,
                            'nationality' => $payment->booking->nationality,
                            'special_request' => $payment->booking->special_request,
                            'user' => $payment->booking->user ? [
                                'id' => $payment->booking->user->id,
                                'name' => $payment->booking->user->name,
                                'email' => $payment->booking->user->email,
                            ] : null,
                            'tour_date' => $payment->booking->tourDate ? [
                                'id' => $payment->booking->tourDate->id,
                                'tour_date' => $payment->booking->tourDate->tour_date,
                                'package' => $payment->booking->tourDate->package ? [
                                    'id' => $payment->booking->tourDate->package->id,
                                    'package_name' => $payment->booking->tourDate->package->package_name,
                                    'destination' => $payment->booking->tourDate->package->destination,
                                    'price' => $payment->booking->tourDate->package->price,
                                ] : null,
                            ] : null,
                            'pickup_schedule' => $payment->booking->pickupSchedule ? [
                                'id' => $payment->booking->pickupSchedule->id,
                                'pickup_time' => $payment->booking->pickupSchedule->pickup_time,
                                'pickup_location' => $payment->booking->pickupSchedule->pickupLocation ? [
                                    'id' => $payment->booking->pickupSchedule->pickupLocation->id,
                                    'name' => $payment->booking->pickupSchedule->pickupLocation->name,
                                ] : null,
                            ] : null,
                        ] : null,
                    ];
                })
        );
    }

    public function export(): StreamedResponse
    {
        $payments = Payment::query()
            ->with([
                'booking.user',
                'booking.tourDate.package',
            ])
            ->latest()
            ->get();

        $rows = $payments->map(function (Payment $payment): array {
            return [
                $payment->id,
                $payment->booking_id,
                $payment->booking?->user?->name,
                $payment->booking?->user?->email,
                $payment->booking?->phone_number,
                $payment->booking?->tourDate?->package?->package_name,
                $payment->booking?->tourDate?->package?->destination,
                $payment->booking?->tourDate?->tour_date?->toDateString(),
                $payment->booking?->number_of_guests,
                $payment->amount,
                $payment->payment_method,
                $payment->payment_status,
                $payment->transaction_reference,
                $payment->paid_at?->toDateTimeString(),
                $payment->notes,
            ];
        });

        return CsvDownload::stream(
            'payments-'.now()->format('Y-m-d').'.csv',
            [
                'ID',
                'Booking',
                'Customer',
                'Email',
                'Phone',
                'Package',
                'Destination',
                'Tour Date',
                'Guests',
                'Amount',
                'Method',
                'Status',
                'Reference',
                'Paid At',
                'Notes',
            ],
            $rows,
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Show
    |--------------------------------------------------------------------------
    */

    public function show(Payment $payment): JsonResponse
    {
        $payment->load([
            'booking.user',
            'booking.tourDate.package',
            'booking.pickupSchedule.pickupLocation',
        ]);

        $proofFiles = $this->files->filesWithMeta(
            UploadPath::payment($payment->booking_id)
        );

        $proof = $proofFiles[0] ?? null;

        return response()->json([
            'id' => $payment->id,
            'booking_id' => $payment->booking_id,
            'amount' => (float) $payment->amount,
            'payment_method' => $payment->payment_method,
            'payment_status' => $payment->payment_status,
            'transaction_reference' => $payment->transaction_reference,
            'proof_of_payment_url' => $proof['url'] ?? null,
            'proof_of_payment_is_image' => $proof['is_image'] ?? null,
            'proof_files' => $proofFiles,
            'notes' => $payment->notes,
            'paid_at' => $payment->paid_at?->format('Y-m-d H:i:s'),
            'created_at' => $payment->created_at?->format('Y-m-d H:i:s'),
            'booking' => $payment->booking,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Store
    |--------------------------------------------------------------------------
    */

    public function store(StorePaymentRequest $request): never
    {
        abort(403, 'Payments are recorded by PayMongo or by the tourist cash choice.');
    }

    /*
    |--------------------------------------------------------------------------
    | Update
    |--------------------------------------------------------------------------
    */

    public function update(UpdatePaymentRequest $request, Payment $payment): never
    {
        abort(403, 'Payments can only be viewed.');
    }

    /*
    |--------------------------------------------------------------------------
    | Destroy
    |--------------------------------------------------------------------------
    */

    public function destroy(Payment $payment): never
    {
        abort(403, 'Payments can only be viewed.');
    }
}
