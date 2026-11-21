<?php

declare(strict_types=1);

namespace Alsuhaibi\SaudiPayments\Tests\Feature;

use Alsuhaibi\SaudiPayments\Drivers\TabbyGateway;
use Alsuhaibi\SaudiPayments\Exceptions\PaymentMethodUnavailableException;
use Alsuhaibi\SaudiPayments\PaymentManager;
use Alsuhaibi\SaudiPayments\Support\Money;
use Alsuhaibi\SaudiPayments\Tests\TestCase;
use Illuminate\Support\Facades\Http;

final class PaymentManagerTest extends TestCase
{
    private function manager(): PaymentManager
    {
        return $this->app->make(PaymentManager::class);
    }

    public function test_enabled_method_within_limits_resolves_its_gateway(): void
    {
        $this->paymentMethod('tabby', ['min_amount_halalas' => 10_000, 'max_amount_halalas' => 500_000]);

        $gateway = $this->manager()->gatewayFor('tabby', Money::halalas(14_950));

        $this->assertInstanceOf(TabbyGateway::class, $gateway);
    }

    public function test_disabled_method_is_rejected(): void
    {
        $this->paymentMethod('tamara', ['enabled' => false]);

        $this->expectException(PaymentMethodUnavailableException::class);
        $this->expectExceptionMessage('not available');

        $this->manager()->gatewayFor('tamara', Money::halalas(14_950));
    }

    public function test_method_missing_from_the_database_is_rejected(): void
    {
        $this->expectException(PaymentMethodUnavailableException::class);

        $this->manager()->gatewayFor('moyasar', Money::halalas(14_950));
    }

    public function test_amount_below_minimum_is_rejected(): void
    {
        $this->paymentMethod('tabby', ['min_amount_halalas' => 10_000]);

        $this->expectException(PaymentMethodUnavailableException::class);
        $this->expectExceptionMessage('at least 10000 halalas');

        $this->manager()->gatewayFor('tabby', Money::halalas(9_999));
    }

    public function test_amount_above_maximum_is_rejected(): void
    {
        $this->paymentMethod('mispay', ['max_amount_halalas' => 300_000]);

        $this->expectException(PaymentMethodUnavailableException::class);
        $this->expectExceptionMessage('at most 300000 halalas');

        $this->manager()->gatewayFor('mispay', Money::halalas(300_001));
    }

    public function test_limits_are_inclusive(): void
    {
        $this->paymentMethod('tabby', ['min_amount_halalas' => 10_000, 'max_amount_halalas' => 20_000]);

        $this->manager()->gatewayFor('tabby', Money::halalas(10_000));
        $this->manager()->gatewayFor('tabby', Money::halalas(20_000));

        $this->addToAssertionCount(2);
    }

    public function test_available_methods_are_filtered_and_ordered(): void
    {
        $this->paymentMethod('moyasar', ['sort_order' => 1]);
        $this->paymentMethod('tabby', ['sort_order' => 2, 'min_amount_halalas' => 10_000]);
        $this->paymentMethod('tamara', ['sort_order' => 3, 'enabled' => false]);
        $this->paymentMethod('paytabs', ['sort_order' => 0, 'max_amount_halalas' => 1_000]);
        $this->paymentMethod('not-configured', ['sort_order' => 0]);

        $keys = $this->manager()->availableMethods(Money::halalas(14_950))->pluck('key')->all();

        $this->assertSame(['moyasar', 'tabby'], $keys);
    }

    public function test_checkout_checks_availability_then_calls_the_gateway(): void
    {
        Http::fake(['api.moyasar.com/*' => Http::response(['id' => 'inv_1', 'url' => 'https://checkout.moyasar.com/invoices/inv_1'])]);
        $this->paymentMethod('moyasar');

        $session = $this->manager()->checkout('moyasar', $this->checkoutRequest());

        $this->assertSame('inv_1', $session->providerReference);
        Http::assertSentCount(1);
    }

    public function test_disabled_method_never_reaches_the_provider(): void
    {
        Http::fake();
        $this->paymentMethod('moyasar', ['enabled' => false]);

        try {
            $this->manager()->checkout('moyasar', $this->checkoutRequest());
            $this->fail('Expected PaymentMethodUnavailableException');
        } catch (PaymentMethodUnavailableException) {
            Http::assertNothingSent();
        }
    }
}
