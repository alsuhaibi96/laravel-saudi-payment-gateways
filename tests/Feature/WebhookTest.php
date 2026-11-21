<?php

declare(strict_types=1);

namespace Alsuhaibi\SaudiPayments\Tests\Feature;

use Alsuhaibi\SaudiPayments\Enums\PaymentStatus;
use Alsuhaibi\SaudiPayments\Events\PaymentStatusUpdated;
use Alsuhaibi\SaudiPayments\Tests\TestCase;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;

final class WebhookTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $payload
     */
    private function postWebhook(string $gateway, array $payload, ?string $signature = null, string $header = 'X-Signature'): TestResponse
    {
        [$body, $validSignature] = $this->signedPayload($gateway, $payload);

        return $this->call(
            'POST',
            "/webhooks/payments/{$gateway}",
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_'.strtoupper(str_replace('-', '_', $header)) => $signature ?? $validSignature,
            ],
            content: $body,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function moyasarPaid(): array
    {
        return [
            'id' => 'evt_001',
            'type' => 'payment_paid',
            'data' => [
                'id' => 'pay_789',
                'status' => 'paid',
                'amount' => 14_950,
                'currency' => 'SAR',
                'metadata' => ['order_reference' => 'ORD-1001'],
            ],
        ];
    }

    public function test_valid_signature_is_accepted_and_dispatches_the_event(): void
    {
        Event::fake([PaymentStatusUpdated::class]);

        $this->postWebhook('moyasar', $this->moyasarPaid())
            ->assertOk()
            ->assertJson(['received' => true, 'duplicate' => false]);

        Event::assertDispatched(PaymentStatusUpdated::class, fn (PaymentStatusUpdated $event): bool => $event->result->orderReference === 'ORD-1001'
            && $event->result->status === PaymentStatus::Paid
            && $event->result->amount?->halalas === 14_950
            && $event->result->providerPaymentId === 'pay_789');

        $this->assertDatabaseHas('payment_webhook_events', [
            'gateway' => 'moyasar', 'event_id' => 'evt_001', 'status' => 'paid', 'amount_halalas' => 14_950,
        ]);
    }

    public function test_invalid_signature_is_rejected(): void
    {
        Event::fake([PaymentStatusUpdated::class]);

        $this->postWebhook('moyasar', $this->moyasarPaid(), signature: str_repeat('0', 64))
            ->assertUnauthorized();

        Event::assertNotDispatched(PaymentStatusUpdated::class);
        $this->assertDatabaseCount('payment_webhook_events', 0);
    }

    public function test_missing_signature_is_rejected(): void
    {
        $this->postJson('/webhooks/payments/moyasar', $this->moyasarPaid())->assertUnauthorized();
    }

    public function test_tampered_body_is_rejected(): void
    {
        [, $signature] = $this->signedPayload('moyasar', $this->moyasarPaid());
        $tampered = $this->moyasarPaid();
        $tampered['data']['amount'] = 100;

        $this->postWebhook('moyasar', $tampered, signature: $signature)->assertUnauthorized();
    }

    public function test_signature_from_another_gateway_secret_is_rejected(): void
    {
        [, $tabbySignature] = $this->signedPayload('tabby', $this->moyasarPaid());

        $this->postWebhook('moyasar', $this->moyasarPaid(), signature: $tabbySignature)->assertUnauthorized();
    }

    public function test_empty_secret_never_verifies(): void
    {
        config()->set('saudi-payments.gateways.moyasar.webhook_secret', '');
        $body = json_encode($this->moyasarPaid(), JSON_THROW_ON_ERROR);

        $this->postWebhook('moyasar', $this->moyasarPaid(), signature: hash_hmac('sha256', $body, ''))->assertUnauthorized();
    }

    public function test_duplicate_events_are_processed_once(): void
    {
        Event::fake([PaymentStatusUpdated::class]);

        $this->postWebhook('moyasar', $this->moyasarPaid())->assertJson(['duplicate' => false]);
        $this->postWebhook('moyasar', $this->moyasarPaid())->assertOk()->assertJson(['duplicate' => true]);
        $this->postWebhook('moyasar', $this->moyasarPaid())->assertOk()->assertJson(['duplicate' => true]);

        Event::assertDispatchedTimes(PaymentStatusUpdated::class, 1);
        $this->assertDatabaseCount('payment_webhook_events', 1);
    }

    public function test_same_event_id_on_different_gateways_is_not_a_duplicate(): void
    {
        Event::fake([PaymentStatusUpdated::class]);

        $this->postWebhook('moyasar', $this->moyasarPaid())->assertOk();
        $this->postWebhook('mispay', ['checkoutId' => 'evt_001', 'orderId' => 'ORD-2', 'status' => 'success', 'amount' => '10.00'])
            ->assertOk()
            ->assertJson(['duplicate' => false]);

        Event::assertDispatchedTimes(PaymentStatusUpdated::class, 2);
    }

    public function test_failing_listener_rolls_back_so_the_retry_is_processed(): void
    {
        $calls = 0;
        Event::listen(PaymentStatusUpdated::class, function () use (&$calls): void {
            $calls++;
            if ($calls === 1) {
                throw new \RuntimeException('Database hiccup');
            }
        });

        $this->withoutExceptionHandling();

        try {
            $this->postWebhook('moyasar', $this->moyasarPaid());
            $this->fail('Expected the listener exception');
        } catch (\RuntimeException) {
            $this->assertDatabaseCount('payment_webhook_events', 0);
        }

        $this->postWebhook('moyasar', $this->moyasarPaid())->assertJson(['duplicate' => false]);
        $this->assertSame(2, $calls);
    }

    public function test_paytabs_uses_its_signature_header_and_decimal_amounts(): void
    {
        Event::fake([PaymentStatusUpdated::class]);

        $this->postWebhook('paytabs', [
            'tran_ref' => 'TST2400000001',
            'cart_id' => 'ORD-1001',
            'cart_amount' => '149.50',
            'cart_currency' => 'SAR',
            'payment_result' => ['response_status' => 'A'],
        ], header: 'Signature')->assertOk();

        Event::assertDispatched(PaymentStatusUpdated::class, fn (PaymentStatusUpdated $event): bool => $event->result->status === PaymentStatus::Paid
            && $event->result->amount?->halalas === 14_950);
    }

    public function test_bnpl_authorization_is_not_treated_as_paid(): void
    {
        Event::fake([PaymentStatusUpdated::class]);

        $this->postWebhook('tabby', [
            'id' => 'pay_tabby_1', 'status' => 'AUTHORIZED', 'amount' => '149.50', 'currency' => 'SAR',
            'order' => ['reference_id' => 'ORD-1001'],
        ])->assertOk();

        $this->postWebhook('tamara', [
            'order_id' => 'tmr_1', 'order_reference_id' => 'ORD-1002', 'order_status' => 'approved',
            'total_amount' => ['amount' => '200.00', 'currency' => 'SAR'],
        ])->assertOk();

        Event::assertDispatched(PaymentStatusUpdated::class, fn (PaymentStatusUpdated $e): bool => $e->result->gateway === 'tabby' && $e->result->status === PaymentStatus::Authorized);
        Event::assertDispatched(PaymentStatusUpdated::class, fn (PaymentStatusUpdated $e): bool => $e->result->gateway === 'tamara' && $e->result->status === PaymentStatus::Authorized);
    }

    public function test_unparseable_payload_returns_422(): void
    {
        $this->postWebhook('moyasar', ['id' => 'evt_x', 'data' => ['status' => 'paid']])->assertStatus(422);
    }

    public function test_unknown_gateway_returns_404(): void
    {
        $this->postWebhook('moyasar', $this->moyasarPaid())->assertOk();
        $this->postJson('/webhooks/payments/stripe', [])->assertNotFound();
    }
}
