<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Services\Billing\WebhookProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public, stateless endpoint for Stripe / Wave. No session, no CSRF, no login - authenticity comes ONLY from the
 * provider signature (or Wave shared secret) verified over the raw body inside {@see WebhookProcessor}.
 */
class WebhookController extends Controller
{
    public function __invoke(Request $request, string $provider, WebhookProcessor $processor): JsonResponse
    {
        [$status, $message] = $processor->handle($provider, $request->getContent(), $request->headers->all());

        return response()->json(['status' => $message], $status);
    }
}
