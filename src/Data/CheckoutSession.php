<?php

declare(strict_types=1);

namespace Alsuhaibi\SaudiPayments\Data;

/**
 * A hosted checkout created at the provider. Redirect the customer to $redirectUrl.
 */
final class CheckoutSession
{
    /**
     * @param  array<string, mixed>  $raw  the provider's response, for logging/debugging
     */
    public function __construct(
        public readonly string $gateway,
        public readonly string $providerReference,
        public readonly string $redirectUrl,
        public readonly array $raw = [],
    ) {
    }
}
