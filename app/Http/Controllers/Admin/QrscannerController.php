<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\FileUploadService;
use App\Services\UploadPath;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class QrscannerController extends Controller
{
    public function __construct(
        private FileUploadService $files
    ) {}

    /**
     * QR Scanner page.
     */
    public function index(): Response
    {
        return Inertia::render(
            'admin/qr-scanner/Index'
        );
    }

    /**
     * Verify a booking QR token.
     *
     * Returns JSON with the redirect URL to the dedicated verified booking page.
     */
    public function verify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'qr_token' => [
                'required',
                'string',
                'max:255',
            ],
        ]);

        $booking = Booking::query()
            ->where(
                'qr_token',
                $validated['qr_token']
            )
            ->first();

        if (! $booking) {
            return response()->json([
                'success' => false,
                'message' => 'QR code is not associated with a valid booking.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Booking verified successfully.',
            'booking_id' => $booking->id,
            'redirect_url' => route('admin.qrscanner.show', $booking),
        ]);
    }

    /**
     * Display the verified booking details.
     */
    public function show(Booking $booking): Response
    {
        $booking->load([
            'user',
            'tourDate.package',
            'tourDate.times',
            'pickupSchedule.pickupLocation',
            'payments',
        ]);

        $package = $booking->tourDate?->package;
        $packagePayload = null;

        if ($package) {
            $packagePayload = [
                ...$package->toArray(),
                'image_urls' => array_column(
                    $this->files->images(
                        UploadPath::package($package->id)
                    ),
                    'url'
                ),
            ];
        }

        $proofFiles = $this->files->filesWithMeta(
            UploadPath::payment($booking->id)
        );

        $proof = $proofFiles[0] ?? null;

        $payments = $booking->payments->map(
            function ($payment) use ($proof) {
                return [
                    'id' => $payment->id,
                    'amount' => $payment->amount,
                    'payment_method' => $payment->payment_method,
                    'payment_status' => $payment->payment_status,
                    'transaction_reference' => $payment->transaction_reference,
                    'proof_of_payment_url' => $proof['url'] ?? null,
                    'proof_of_payment_is_image' => $proof['is_image'] ?? null,
                    'notes' => $payment->notes,
                    'paid_at' => $payment->paid_at,
                    'created_at' => $payment->created_at,
                    'updated_at' => $payment->updated_at,
                ];
            }
        )->values();

        $packageImages = [];
        if ($package) {
            $packageImages = $this->files->images(
                UploadPath::package($package->id)
            );
        }

        $paymentProofs = $this->files->images(
            UploadPath::payment($booking->id)
        );

        return Inertia::render(
            'admin/qr-scanner/VerifyShow',
            [
                'booking' => [
                    'id' => $booking->id,
                    'booking_status' => $booking->booking_status,
                    'qr_token' => $booking->qr_token,
                    'number_of_guests' => $booking->number_of_guests,
                    'phone_number' => $booking->phone_number,
                    'nationality' => $booking->nationality,
                    'special_request' => $booking->special_request,
                    'created_at' => $booking->created_at,
                    'updated_at' => $booking->updated_at,
                    'user' => $booking->user,
                    'package' => $packagePayload,
                    'tour_date' => $booking->tourDate,
                    'pickup_schedule' => $booking->pickupSchedule,
                    'payments' => $payments,
                    'amount_received' => $booking->amountReceived(),
                    'remaining_balance' => $booking->remainingBalance(),
                    'package_images' => $packageImages,
                    'payment_proofs' => $paymentProofs,
                ],
            ]
        );
    }
}
