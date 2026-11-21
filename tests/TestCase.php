<?php

declare(strict_types=1);

namespace Alsuhaibi\SaudiPayments\Tests;

use Alsuhaibi\SaudiPayments\Data\CheckoutRequest;
use Alsuhaibi\SaudiPayments\Data\Customer;
use Alsuhaibi\SaudiPayments\Models\PaymentMethod;
use Alsuhaibi\SaudiPayments\SaudiPaymentsServiceProvider;
use Alsuhaibi\SaudiPayments\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [SaudiPaymentsServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('saudi-payments.http.retries', 0);

        foreach (['moyasar', 'paytabs', 'tabby', 'tamara', 'mispay'] as $key) {
            $app['config']->set("saudi-payments.gateways.{$key}.webhook_secret", "test-webhook-secret-{$key}");
        }

        $app['config']->set('saudi-payments.gateways.moyasar.secret_key', 'sk_test_moyasar');
        $app['config']->set('saudi-payments.gateways.paytabs.profile_id', '12345');
        $app['config']->set('saudi-payments.gateways.paytabs.server_key', 'test-server-key');
        $app['config']->set('saudi-payments.gateways.tabby.secret_key', 'sk_test_tabby');
        $app['config']->set('saudi-payments.gateways.tabby.merchant_code', 'test_merchant');
        $app['config']->set('saudi-payments.gateways.tamara.api_token', 'test-tamara-token');
        $app['config']->set('saudi-payments.gateways.mispay.app_id', 'test-app-id');
        $app['config']->set('saudi-payments.gateways.mispay.app_secret', 'test-app-secret');
    }

    protected function checkoutRequest(int $halalas = 14_950, string $reference = 'ORD-1001'): CheckoutRequest
    {
        return new CheckoutRequest(
            orderReference: $reference,
            amount: Money::halalas($halalas),
            customer: new Customer('Sara Alqahtani', '+966500000000', 'sara@example.com'),
            description: 'Home cleaning, 3 hours',
            successUrl: 'https://shop.example.com/checkout/success',
            cancelUrl: 'https://shop.example.com/checkout/cancel',
            webhookUrl: 'https://shop.example.com/webhooks/payments/test',
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function paymentMethod(string $key, array $attributes = []): PaymentMethod
    {
        return PaymentMethod::query()->create($attributes + [
            'key' => $key,
            'name_ar' => "طريقة {$key}",
            'name_en' => ucfirst($key),
            'enabled' => true,
            'sort_order' => 0,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{0: string, 1: string} [raw json body, hex HMAC signature]
     */
    protected function signedPayload(string $gateway, array $payload): array
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);

        return [$body, hash_hmac('sha256', $body, "test-webhook-secret-{$gateway}")];
    }
}
