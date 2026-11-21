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
 * Tabby (buy now, pay later) hosted checkout.
 *
 * Simplified mapping: adapt to Tabby's current API docs (checkout session, webhook auth, capture).
 */
final class TabbyGateway extends AbstractHttpGateway
{
    public function createCheckout(CheckoutRequest $request): CheckoutSession
    {
        $client = $this->client()->withToken((string) $this->config['secret_key']);

        $response = $this->post($client, '/api/v2/checkout', [
            'payment' => [
                'amount' => $request->amount->toDecimal(),
                'currency' => $request->amount->currency,
                'description' => $request->description,
                'buyer' => [
                    'name' => $request->customer->name,
                    'phone' => $request->customer->phone,
                    'email' => $request->customer->email,
                ],
                'order' => ['reference_id' => $request->orderReference],
            ],
            'lang' => $request->locale,
            'merchant_code' => $this->config['merchant_code'],
            'merchant_urls' => [
                'success' => $request->successUrl,
                'cancel' => $request->cancelUrl,
                'failure' => $request->cancelUrl,
            ],
        ]);

        return new CheckoutSession(
            gateway: $this->key,
            providerReference: (string) $this->requireField($response, 'id'),
            redirectUrl: (string) $this->requireField($response, 'configuration.available_products.installments.0.web_url'),
            raw: $response,
        );
    }

    public function parseWebhook(Request $request): PaymentResult
    {
        $payload = $request->json()->all();
        $paymentId = $this->requireWebhookField($payload, 'id');
        $status = strtolower((string) data_get($payload, 'status', ''));

        return new PaymentResult(
            gateway: $this->key,
            eventId: $paymentId.':'.$status,
            orderReference: $this->requireWebhookField($payload, 'order.reference_id'),
            status: $this->mapStatus($status, [
                // "authorized" still needs a capture call on Tabby's side.
                'authorized' => PaymentStatus::Authorized,
                'closed' => PaymentStatus::Paid,
                'rejected' => PaymentStatus::Failed,
                'expired' => PaymentStatus::Cancelled,
            ]),
            amount: Money::fromDecimal((string) data_get($payload, 'amount', '0'), (string) data_get($payload, 'currency', 'SAR')),
            providerPaymentId: $paymentId,
            raw: $payload,
        );
    }
}
