<?php

declare(strict_types=1);

namespace Alsuhaibi\SaudiPayments;

use Alsuhaibi\SaudiPayments\Contracts\PaymentGateway;
use Alsuhaibi\SaudiPayments\Data\CheckoutRequest;
use Alsuhaibi\SaudiPayments\Data\CheckoutSession;
use Alsuhaibi\SaudiPayments\Exceptions\PaymentMethodUnavailableException;
use Alsuhaibi\SaudiPayments\Models\PaymentMethod;
use Alsuhaibi\SaudiPayments\Support\Money;
use Illuminate\Database\Eloquent\Collection;

/**
 * Entry point for the app: which methods can this customer use, and start a checkout with one.
 * Availability comes from the payment_methods table, not from code or the mobile app.
 */
final class PaymentManager
{
    public function __construct(private readonly GatewayFactory $factory)
    {
    }

    /**
     * Enabled methods that accept this amount and have a configured gateway, in display order.
     *
     * @return Collection<int, PaymentMethod>
     */
    public function availableMethods(Money $amount): Collection
    {
        return PaymentMethod::query()
            ->enabled()
            ->acceptingAmount($amount->halalas)
            ->ordered()
            ->get()
            ->filter(fn (PaymentMethod $method): bool => $this->factory->has($method->key))
            ->values();
    }

    /**
     * Resolve the gateway for a method key after checking it is enabled and accepts the amount.
     *
     * @throws PaymentMethodUnavailableException
     */
    public function gatewayFor(string $methodKey, Money $amount): PaymentGateway
    {
        $method = PaymentMethod::query()->where('key', $methodKey)->first();

        if ($method === null || ! $method->enabled || ! $this->factory->has($methodKey)) {
            throw PaymentMethodUnavailableException::disabled($methodKey);
        }

        if ($method->min_amount_halalas !== null && $amount->halalas < $method->min_amount_halalas) {
            throw PaymentMethodUnavailableException::belowMinimum($methodKey, $method->min_amount_halalas);
        }

        if ($method->max_amount_halalas !== null && $amount->halalas > $method->max_amount_halalas) {
            throw PaymentMethodUnavailableException::aboveMaximum($methodKey, $method->max_amount_halalas);
        }

        return $this->factory->make($methodKey);
    }

    public function checkout(string $methodKey, CheckoutRequest $request): CheckoutSession
    {
        return $this->gatewayFor($methodKey, $request->amount)->createCheckout($request);
    }
}
