<?php

declare(strict_types=1);

namespace Alsuhaibi\SaudiPayments\Tests\Feature;

use Alsuhaibi\SaudiPayments\Contracts\PaymentGateway;
use Alsuhaibi\SaudiPayments\Drivers\MisPayGateway;
use Alsuhaibi\SaudiPayments\Drivers\MoyasarGateway;
use Alsuhaibi\SaudiPayments\Drivers\PayTabsGateway;
use Alsuhaibi\SaudiPayments\Drivers\TabbyGateway;
use Alsuhaibi\SaudiPayments\Drivers\TamaraGateway;
use Alsuhaibi\SaudiPayments\Exceptions\UnsupportedGatewayException;
use Alsuhaibi\SaudiPayments\GatewayFactory;
use Alsuhaibi\SaudiPayments\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class GatewayFactoryTest extends TestCase
{
    /**
     * @return iterable<string, array{string, class-string<PaymentGateway>}>
     */
    public static function drivers(): iterable
    {
        yield 'moyasar' => ['moyasar', MoyasarGateway::class];
        yield 'paytabs' => ['paytabs', PayTabsGateway::class];
        yield 'tabby' => ['tabby', TabbyGateway::class];
        yield 'tamara' => ['tamara', TamaraGateway::class];
        yield 'mispay' => ['mispay', MisPayGateway::class];
    }

    /**
     * @param  class-string<PaymentGateway>  $class
     */
    #[DataProvider('drivers')]
    public function test_it_resolves_each_driver_by_key(string $key, string $class): void
    {
        $gateway = $this->app->make(GatewayFactory::class)->make($key);

        $this->assertInstanceOf($class, $gateway);
        $this->assertSame($key, $gateway->key());
    }

    public function test_it_reuses_resolved_instances(): void
    {
        $factory = $this->app->make(GatewayFactory::class);

        $this->assertSame($factory->make('tabby'), $factory->make('tabby'));
    }

    public function test_unknown_gateway_throws(): void
    {
        $this->expectException(UnsupportedGatewayException::class);

        $this->app->make(GatewayFactory::class)->make('unknown');
    }

    public function test_driver_must_implement_the_contract(): void
    {
        config()->set('saudi-payments.gateways.broken', ['driver' => \stdClass::class]);

        $this->expectException(UnsupportedGatewayException::class);

        $this->app->make(GatewayFactory::class)->make('broken');
    }

    public function test_custom_gateways_can_be_registered(): void
    {
        $custom = $this->createStub(PaymentGateway::class);
        $factory = $this->app->make(GatewayFactory::class);

        $factory->extend('local-wallet', fn (): PaymentGateway => $custom);

        $this->assertTrue($factory->has('local-wallet'));
        $this->assertSame($custom, $factory->make('local-wallet'));
        $this->assertContains('local-wallet', $factory->keys());
    }
}
