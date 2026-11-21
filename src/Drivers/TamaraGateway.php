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
 * Tamara (buy now, pay later) hosted checkout.
 *
 * Simplified mapping: adapt to Tamara's current API docs (checkout, notification token, authorise/capture).
 */
final class TamaraGateway extends AbstractHttpGateway
{
    public function createCheckout(CheckoutRequest $request): CheckoutSession
    {
        $client = $this->client()->withToken((string) $this->config['api_token']);

        $response = $this->post($client, '/checkout', [
            'order_reference_id' => $request->orderReference,
            'total_amount' => [
                'amount' => $request->amount->toDecimal(),
                'currency' => $request->amount->currency,
            ],
            'description' => $request->description,
            'country_code' => 'SA',
            'payment_type' => 'PAY_BY_INSTALMENTS',
            'locale' => $request->locale === 'ar' ? 'ar_SA' : 'en_US',
            'consumer' => [
                'first_name' => $request->customer->firstName(),
                'last_name' => $request->customer->lastName(),
                'phone_number' => $request->customer->phone,
                'email' => $request->customer->email,
            ],
            'merchant_url' => [
                'success' => $request->successUrl,
                'failure' => $request->cancelUrl,
                'cancel' => $request->cancelUrl,
                'notification' => $request->webhookUrl,
            ],
        ]);

        return new CheckoutSession(
            gateway: $this->key,
            providerReference: (string) $this->requireField($response, 'order_id'),
            redirectUrl: (string) $this->requireField($response, 'checkout_url'),
            raw: $response,
        );
    }

    public function parseWebhook(Request $request): PaymentResult
    {
        $payload = $request->json()->all();
        $orderId = $this->requireWebhookField($payload, 'order_id');
        $status = strtolower((string) data_get($payload, 'order_status', ''));

        return new PaymentResult(
            gateway: $this->key,
            eventId: $orderId.':'.$status,
            orderReference: $this->requireWebhookField($payload, 'order_reference_id'),
            status: $this->mapStatus($status, [
                'approved' => PaymentStatus::Authorized,
                'authorised' => PaymentStatus::Authorized,
                'fully_captured' => PaymentStatus::Paid,
                'declined' => PaymentStatus::Failed,
                'expired' => PaymentStatus::Cancelled,
                'canceled' => PaymentStatus::Cancelled,
                'fully_refunded' => PaymentStatus::Refunded,
            ]),
            amount: data_get($payload, 'total_amount.amount') === null
                ? null
                : Money::fromDecimal((string) data_get($payload, 'total_amount.amount'), (string) data_get($payload, 'total_amount.currency', 'SAR')),
            providerPaymentId: $orderId,
            raw: $payload,
        );
    }
}
