<?php

declare(strict_types=1);

namespace Alsuhaibi\SaudiPayments\Http\Controllers;

use Alsuhaibi\SaudiPayments\Exceptions\InvalidWebhookException;
use Alsuhaibi\SaudiPayments\GatewayFactory;
use Alsuhaibi\SaudiPayments\WebhookProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * POST /webhooks/payments/{gateway}
 *
 * The only place a payment becomes "paid". The browser redirect to success_url is UX only:
 * customers close tabs and lose signal, and redirects can be forged.
 */
final class WebhookController extends Controller
{
    public function __invoke(Request $request, string $gateway, GatewayFactory $factory, WebhookProcessor $processor): JsonResponse
    {
        if (! $factory->has($gateway)) {
            return response()->json(['message' => 'Unknown gateway.'], 404);
        }

        $driver = $factory->make($gateway);

        if (! $driver->verifyWebhook($request)) {
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        try {
            $result = $driver->parseWebhook($request);
        } catch (InvalidWebhookException $e) {
            report($e);

            // 422, not 5xx: retrying an unparseable payload will not fix it.
            return response()->json(['message' => 'Unprocessable payload.'], 422);
        }

        $isNew = $processor->process($result);

        return response()->json(['received' => true, 'duplicate' => ! $isNew]);
    }
}
