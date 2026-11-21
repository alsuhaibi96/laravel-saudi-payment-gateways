<?php

declare(strict_types=1);

namespace Alsuhaibi\SaudiPayments\Drivers;

use Alsuhaibi\SaudiPayments\Data\CheckoutRequest;
use Alsuhaibi\SaudiPayments\Data\CheckoutSession;
use Alsuhaibi\SaudiPayments\Data\PaymentResult;
use Alsuhaibi\SaudiPayments\Enums\PaymentStatus;
use Alsuhaibi\SaudiPayments\Support\Money;
use Illuminate\Http\Request;

/**
 * MISpay (buy now, pay later) checkout.
 *
 * Simplified mapping: MISpay's API (auth flow, payload encryption, field names) differs from
 * this sketch. Adapt to MISpay's current merchant API docs before going live.
 */
final class MisPayGateway extends AbstractHttpGateway
{
    public function createCheckout(CheckoutRequest $request): CheckoutSession
    {
        $client = $this->client()->withHeaders([
            'x-app-id' => (string) $this->config['app_id'],
            'x-app-secret' => (string) $this->config['app_secret'],
        ]);

        $response = $this->post($client, '/checkouts', [
            'orderId' => $request->orderReference,
            'purchaseAmount' => $request->amount->toDecimal(),
            'purchaseCurrency' => $request->amount->currency,
            'lang' => $request->locale,
            'callbackUri' => $request->successUrl,
            'cancelUri' => $request->cancelUrl,
            'customer' => [
                'name' => $request->customer->name,
                'mobile' => $request->customer->phone,
                'email' => $request->customer->email,
            ],
        ]);

        return new CheckoutSession(
            gateway: $this->key,
            providerReference: (string) $this->requireField($response, 'result.checkoutId'),
            redirectUrl: (string) $this->requireField($response, 'result.url'),
            raw: $response,
        );
    }

    public function parseWebhook(Request $request): PaymentResult
    {
        $payload = $request->json()->all();
        $checkoutId = $this->requireWebhookField($payload, 'checkoutId');
        $status = strtolower((string) data_get($payload, 'status', ''));

        return new PaymentResult(
            gateway: $this->key,
            eventId: $checkoutId.':'.$status,
            orderReference: $this->requireWebhookField($payload, 'orderId'),
            status: $this->mapStatus($status, [
                'success' => PaymentStatus::Paid,
                'completed' => PaymentStatus::Paid,
                'failed' => PaymentStatus::Failed,
                'canceled' => PaymentStatus::Cancelled,
                'cancelled' => PaymentStatus::Cancelled,
            ]),
            amount: data_get($payload, 'amount') === null
                ? null
                : Money::fromDecimal((string) data_get($payload, 'amount'), 'SAR'),
            providerPaymentId: $checkoutId,
            raw: $payload,
        );
    }
}
