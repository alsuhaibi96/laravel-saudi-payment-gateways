<?php

declare(strict_types=1);

namespace Alsuhaibi\SaudiPayments\Data;

use Alsuhaibi\SaudiPayments\Support\Money;

/**
 * Everything a gateway needs to open a hosted checkout for one order.
 */
final class CheckoutRequest
{
    /**
     * @param  string  $orderReference  your unique order id; also used to make webhooks idempotent
     * @param  string  $successUrl  where the provider sends the customer's browser after paying
     * @param  string  $cancelUrl  where the provider sends the browser on cancel/failure
     * @param  string|null  $webhookUrl  server-to-server notification URL, if the provider accepts one per request
     * @param  array<string, scalar>  $metadata
     */
    public function __construct(
        public readonly string $orderReference,
        public readonly Money $amount,
        public readonly Customer $customer,
        public readonly string $description,
        public readonly string $successUrl,
        public readonly string $cancelUrl,
        public readonly ?string $webhookUrl = null,
        public readonly string $locale = 'ar',
        public readonly array $metadata = [],
    ) {
    }
}
