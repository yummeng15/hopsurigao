<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentProofController extends Controller
{
    /**
     * Display a payment proof only to an authorized user.
     */
    public function show(
        Request $request,
        Booking $booking,
        string $filename
    ): StreamedResponse {
        /*
        |----------------------------------------------------------
        | Validate Filename
        |----------------------------------------------------------
        */

        abort_if(
            $filename === '' ||
            $filename === '.' ||
            $filename === '..' ||
            str_contains($filename, '/') ||
            str_contains($filename, '\\') ||
            str_contains($filename, "\0"),
            404
        );

        /*
        |----------------------------------------------------------
        | Authentication
        |----------------------------------------------------------
        */

        $user = $request->user();

        abort_unless($user, 401);

        $roleName = $user->role?->name;

        /*
        |----------------------------------------------------------
        | Load Booking's Tour Operator
        |----------------------------------------------------------
        */

        $booking->loadMissing(
            'tourDate.package'
        );

        $package = $booking
            ->tourDate
            ?->package;

        /*
        |----------------------------------------------------------
        | Authorization
        |----------------------------------------------------------
        |
        | Superadmin:
        | Can view payment proofs across the platform.
        |
        | Admin:
        | Can view proofs belonging to their own active operator.
        |
        | User:
        | Can view proofs belonging to their own bookings.
        |
        */

        $authorized = false;

        if ($roleName === 'Superadmin') {
            $authorized = true;
        }

        if ($roleName === 'Admin') {
            $operator = $user->tourOperator;

            $authorized = (
                $operator !== null &&
                (bool) $operator->is_active &&
                $user->tour_operator_id !== null &&
                $package !== null &&
                (int) $package->tour_operator_id ===
                    (int) $user->tour_operator_id
            );
        }

        if ($roleName === 'User') {
            $authorized = (
                (int) $booking->user_id ===
                (int) $user->id
            );
        }

        abort_unless($authorized, 403);

        /*
        |----------------------------------------------------------
        | Read the Private Payment Proof
        |----------------------------------------------------------
        */

        $path = "payments/{$booking->id}/{$filename}";

        $disk = Storage::disk('local');

        abort_unless(
            $disk->exists($path),
            404
        );

        /*
        |----------------------------------------------------------
        | Safe File Response
        |----------------------------------------------------------
        */

        $extension = strtolower(
            pathinfo(
                $filename,
                PATHINFO_EXTENSION
            )
        );

        $inlineTypes = [
            'jpg',
            'jpeg',
            'png',
            'webp',
            'gif',
            'pdf',
        ];

        $disposition = in_array(
            $extension,
            $inlineTypes,
            true
        )
            ? 'inline'
            : 'attachment';

        return $disk->response(
            $path,
            $filename,
            [
                'Cache-Control' => 'private, no-store, max-age=0',
                'X-Content-Type-Options' => 'nosniff',
            ],
            $disposition
        );
    }
}