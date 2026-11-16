<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Services\Payment\SepayWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SepayWebhookController extends Controller
{
    public function __construct(private readonly SepayWebhookService $webhook)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        try {
            $result = $this->webhook->handle($request);
        } catch (\Throwable $e) {
            Log::error('payment.webhook.error', ['message' => $e->getMessage()]);

            return response()->json(['ok' => false], 500);
        }

        return response()->json($result['body'], $result['http']);
    }
}
