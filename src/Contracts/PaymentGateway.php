<?php

declare(strict_types=1);

namespace Alsuhaibi\SaudiPayments\Contracts;

use Alsuhaibi\SaudiPayments\Data\CheckoutRequest;
use Alsuhaibi\SaudiPayments\Data\CheckoutSession;
use Alsuhaibi\SaudiPayments\Data\PaymentResult;
use Illuminate\Http\Request;

/**
 * Strategy: one implementation per provider, interchangeable at runtime.
 */
interface PaymentGateway
{
    /**
     * The key this gateway is registered under (e.g. "moyasar").
     */
    public function key(): string;

    /**
     * Create a hosted checkout at the provider and return where to send the customer.
     *
     * @throws \Alsuhaibi\SaudiPayments\Exceptions\GatewayRequestException
     */
    public function createCheckout(CheckoutRequest $request): CheckoutSession;

    /**
     * Check that an incoming webhook really comes from the provider.
     */
    public function verifyWebhook(Request $request): bool;

    /**
     * Normalize a (verified) webhook into a PaymentResult.
     *
     * @throws \Alsuhaibi\SaudiPayments\Exceptions\InvalidWebhookException
     */
    public function parseWebhook(Request $request): PaymentResult;
}
