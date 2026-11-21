<?php

declare(strict_types=1);

namespace Alsuhaibi\SaudiPayments\Tests\Feature;

use Alsuhaibi\SaudiPayments\Exceptions\GatewayRequestException;
use Alsuhaibi\SaudiPayments\GatewayFactory;
use Alsuhaibi\SaudiPayments\Tests\TestCase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

final class DriversTest extends TestCase
{
    private function factory(): GatewayFactory
    {
        return $this->app->make(GatewayFactory::class);
    }

    public function test_moyasar_sends_halalas_with_basic_auth(): void
    {
        Http::fake(['https://api.moyasar.com/v1/invoices' => Http::response([
            'id' => 'inv_123', 'url' => 'https://checkout.moyasar.com/invoices/inv_123',
        ])]);

        $session = $this->factory()->make('moyasar')->createCheckout($this->checkoutRequest());

        $this->assertSame('inv_123', $session->providerReference);
        $this->assertSame('https://checkout.moyasar.com/invoices/inv_123', $session->redirectUrl);

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === 'https://api.moyasar.com/v1/invoices'
            && $request->hasHeader('Authorization', 'Basic '.base64_encode('sk_test_moyasar:'))
            && $request['amount'] === 14_950
            && $request['currency'] === 'SAR'
            && $request['metadata']['order_reference'] === 'ORD-1001');
    }

    public function test_paytabs_sends_decimal_amount_and_server_key(): void
    {
        Http::fake(['https://secure.paytabs.sa/payment/request' => Http::response([
            'tran_ref' => 'TST2400000001', 'redirect_url' => 'https://secure.paytabs.sa/payment/page/abc',
        ])]);

        $session = $this->factory()->make('paytabs')->createCheckout($this->checkoutRequest());

        $this->assertSame('TST2400000001', $session->providerReference);

        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'test-server-key')
            && $request['profile_id'] === '12345'
            && $request['cart_id'] === 'ORD-1001'
            && $request['cart_amount'] === '149.50'
            && $request['tran_type'] === 'sale');
    }

    public function test_tabby_sends_bearer_token_and_reads_installments_url(): void
    {
        Http::fake(['https://api.tabby.ai/api/v2/checkout' => Http::response([
            'id' => 'sess_1',
            'configuration' => ['available_products' => ['installments' => [['web_url' => 'https://checkout.tabby.ai/sess_1']]]],
        ])]);

        $session = $this->factory()->make('tabby')->createCheckout($this->checkoutRequest());

        $this->assertSame('https://checkout.tabby.ai/sess_1', $session->redirectUrl);

        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer sk_test_tabby')
            && $request['payment']['amount'] === '149.50'
            && $request['payment']['order']['reference_id'] === 'ORD-1001'
            && $request['merchant_code'] === 'test_merchant');
    }

    public function test_tamara_sends_total_amount_and_consumer(): void
    {
        Http::fake(['https://api-sandbox.tamara.co/checkout' => Http::response([
            'order_id' => 'tmr_1', 'checkout_id' => 'chk_1', 'checkout_url' => 'https://checkout-sandbox.tamara.co/chk_1',
        ])]);

        $session = $this->factory()->make('tamara')->createCheckout($this->checkoutRequest());

        $this->assertSame('tmr_1', $session->providerReference);

        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer test-tamara-token')
            && $request['order_reference_id'] === 'ORD-1001'
            && $request['total_amount'] === ['amount' => '149.50', 'currency' => 'SAR']
            && $request['consumer']['first_name'] === 'Sara'
            && $request['consumer']['last_name'] === 'Alqahtani'
            && $request['locale'] === 'ar_SA');
    }

    public function test_mispay_sends_app_credentials(): void
    {
        Http::fake(['https://api.mispay.co/sandbox/v1/checkouts' => Http::response([
            'result' => ['checkoutId' => 'mis_1', 'url' => 'https://checkout.mispay.co/mis_1'],
        ])]);

        $session = $this->factory()->make('mispay')->createCheckout($this->checkoutRequest());

        $this->assertSame('mis_1', $session->providerReference);

        Http::assertSent(fn (Request $request): bool => $request->hasHeader('x-app-id', 'test-app-id')
            && $request['orderId'] === 'ORD-1001'
            && $request['purchaseAmount'] === '149.50');
    }

    public function test_provider_errors_become_gateway_request_exceptions(): void
    {
        Http::fake(['api.tabby.ai/*' => Http::response(['error' => 'merchant_code is invalid'], 400)]);

        $this->expectException(GatewayRequestException::class);
        $this->expectExceptionMessage('merchant_code is invalid');

        $this->factory()->make('tabby')->createCheckout($this->checkoutRequest());
    }

    public function test_missing_redirect_url_is_reported(): void
    {
        Http::fake(['api.moyasar.com/*' => Http::response(['id' => 'inv_1'])]);

        $this->expectException(GatewayRequestException::class);
        $this->expectExceptionMessage('missing [url]');

        $this->factory()->make('moyasar')->createCheckout($this->checkoutRequest());
    }

    public function test_base_url_is_configurable_for_sandboxes(): void
    {
        config()->set('saudi-payments.gateways.paytabs.base_url', 'https://sandbox.paytabs.test/');
        Http::fake(['https://sandbox.paytabs.test/payment/request' => Http::response(['tran_ref' => 'x', 'redirect_url' => 'https://p.test'])]);

        $this->factory()->make('paytabs')->createCheckout($this->checkoutRequest());

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://sandbox.paytabs.test/payment/request');
    }
}
