<?php

namespace App\Http\Controllers;

use App\Services\PayMongoCheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PayMongoWebhookController extends Controller
{
    public function __construct(
        private PayMongoCheckoutService $paymongo
    ) {}

    /**
     * PayMongo reports a finished payment. The signature is checked
     * before any booking is marked paid.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->getContent();

        if (! $this->paymongo->signatureIsValid($payload, $request->header('Paymongo-Signature'))) {
            return response()->json([
                'message' => 'Invalid signature.',
            ], 403);
        }

        $this->paymongo->settleWebhook($payload);

        return response()->json([
            'received' => true,
        ]);
    }
}
