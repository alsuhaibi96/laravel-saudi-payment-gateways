<?php

declare(strict_types=1);

namespace Alsuhaibi\SaudiPayments;

use Alsuhaibi\SaudiPayments\Contracts\PaymentGateway;
use Alsuhaibi\SaudiPayments\Exceptions\UnsupportedGatewayException;
use Closure;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Client\Factory as HttpFactory;

/**
 * Factory: turns a gateway key ("tabby") into a configured PaymentGateway strategy.
 */
final class GatewayFactory
{
    /** @var array<string, PaymentGateway> */
    private array $resolved = [];

    /** @var array<string, Closure(array<string, mixed>, Container): PaymentGateway> */
    private array $customCreators = [];

    public function __construct(
        private readonly Container $container,
        private readonly Config $config,
    ) {
    }

    public function make(string $key): PaymentGateway
    {
        return $this->resolved[$key] ??= $this->build($key);
    }

    public function has(string $key): bool
    {
        return isset($this->customCreators[$key]) || is_array($this->config->get("saudi-payments.gateways.{$key}"));
    }

    /**
     * Register a gateway without touching the package, e.g. a new local provider.
     *
     * @param  Closure(array<string, mixed>, Container): PaymentGateway  $creator
     */
    public function extend(string $key, Closure $creator): self
    {
        $this->customCreators[$key] = $creator;
        unset($this->resolved[$key]);

        return $this;
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_values(array_unique([
            ...array_keys((array) $this->config->get('saudi-payments.gateways', [])),
            ...array_keys($this->customCreators),
        ]));
    }

    private function build(string $key): PaymentGateway
    {
        $settings = (array) $this->config->get("saudi-payments.gateways.{$key}", []);

        if (isset($this->customCreators[$key])) {
            return ($this->customCreators[$key])($settings, $this->container);
        }

        $driver = $settings['driver'] ?? null;

        if (! is_string($driver) || ! is_subclass_of($driver, PaymentGateway::class)) {
            throw UnsupportedGatewayException::forKey($key);
        }

        return $this->container->make($driver, [
            'key' => $key,
            'config' => $settings,
            'http' => $this->container->make(HttpFactory::class),
            'httpOptions' => (array) $this->config->get('saudi-payments.http', []),
        ]);
    }
}
