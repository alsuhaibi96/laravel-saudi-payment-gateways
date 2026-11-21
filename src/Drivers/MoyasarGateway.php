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
 * Moyasar (mada, cards, Apple Pay, STC Pay) via a hosted invoice page.
 *
 * Simplified mapping: adapt to Moyasar's current API docs (invoices/payments, webhook signing).
 */
final class MoyasarGateway extends AbstractHttpGateway
{
    public function createCheckout(CheckoutRequest $request): CheckoutSession
    {
        $client = $this->client()->withBasicAuth((string) $this->config['secret_key'], '');

        $response = $this->post($client, '/invoices', [
            // Moyasar takes amounts in the smallest unit (halalas).
            'amount' => $request->amount->halalas,
            'currency' => $request->amount->currency,
            'description' => $request->description,
            'success_url' => $request->successUrl,
            'back_url' => $request->cancelUrl,
            'callback_url' => $request->webhookUrl,
            'metadata' => ['order_reference' => $request->orderReference] + $request->metadata,
        ]);

        return new CheckoutSession(
            gateway: $this->key,
            providerReference: (string) $this->requireField($response, 'id'),
            redirectUrl: (string) $this->requireField($response, 'url'),
            raw: $response,
        );
    }

    public function parseWebhook(Request $request): PaymentResult
    {
        $payload = $request->json()->all();

        return new PaymentResult(
            gateway: $this->key,
            eventId: $this->requireWebhookField($payload, 'id'),
            orderReference: $this->requireWebhookField($payload, 'data.metadata.order_reference'),
            status: $this->mapStatus((string) data_get($payload, 'data.status', ''), [
                'paid' => PaymentStatus::Paid,
                'captured' => PaymentStatus::Paid,
                'authorized' => PaymentStatus::Authorized,
                'failed' => PaymentStatus::Failed,
                'voided' => PaymentStatus::Cancelled,
                'refunded' => PaymentStatus::Refunded,
            ]),
            amount: Money::halalas((int) data_get($payload, 'data.amount', 0), (string) data_get($payload, 'data.currency', 'SAR')),
            providerPaymentId: data_get($payload, 'data.id'),
            raw: $payload,
        );
    }
}
