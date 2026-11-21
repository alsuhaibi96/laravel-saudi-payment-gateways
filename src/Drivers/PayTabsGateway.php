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
 * PayTabs hosted payment page.
 *
 * Simplified mapping: adapt to PayTabs' current API docs (payment request, IPN/callback signing).
 */
final class PayTabsGateway extends AbstractHttpGateway
{
    public function createCheckout(CheckoutRequest $request): CheckoutSession
    {
        $client = $this->client()->withHeaders(['Authorization' => (string) $this->config['server_key']]);

        $response = $this->post($client, '/payment/request', [
            'profile_id' => $this->config['profile_id'],
            'tran_type' => 'sale',
            'tran_class' => 'ecom',
            'cart_id' => $request->orderReference,
            'cart_currency' => $request->amount->currency,
            // PayTabs takes a decimal amount.
            'cart_amount' => $request->amount->toDecimal(),
            'cart_description' => $request->description,
            'paypage_lang' => $request->locale,
            'customer_details' => [
                'name' => $request->customer->name,
                'phone' => $request->customer->phone,
                'email' => $request->customer->email,
            ],
            'callback' => $request->webhookUrl,
            'return' => $request->successUrl,
        ]);

        return new CheckoutSession(
            gateway: $this->key,
            providerReference: (string) $this->requireField($response, 'tran_ref'),
            redirectUrl: (string) $this->requireField($response, 'redirect_url'),
            raw: $response,
        );
    }

    public function parseWebhook(Request $request): PaymentResult
    {
        $payload = $request->json()->all();
        $tranRef = $this->requireWebhookField($payload, 'tran_ref');

        return new PaymentResult(
            gateway: $this->key,
            // One callback per transaction status, so tran_ref + status is a stable event id.
            eventId: $tranRef.':'.data_get($payload, 'payment_result.response_status', ''),
            orderReference: $this->requireWebhookField($payload, 'cart_id'),
            status: $this->mapStatus((string) data_get($payload, 'payment_result.response_status', ''), [
                'a' => PaymentStatus::Paid,
                'h' => PaymentStatus::Authorized,
                'p' => PaymentStatus::Pending,
                'd' => PaymentStatus::Failed,
                'e' => PaymentStatus::Failed,
                'v' => PaymentStatus::Cancelled,
            ]),
            amount: Money::fromDecimal((string) data_get($payload, 'cart_amount', '0'), (string) data_get($payload, 'cart_currency', 'SAR')),
            providerPaymentId: $tranRef,
            raw: $payload,
        );
    }
}
