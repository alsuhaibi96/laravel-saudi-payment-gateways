<?php

declare(strict_types=1);

namespace Alsuhaibi\SaudiPayments\Data;

use Alsuhaibi\SaudiPayments\Enums\PaymentStatus;
use Alsuhaibi\SaudiPayments\Support\Money;

/**
 * Normalized outcome parsed from a verified webhook.
 */
final class PaymentResult
{
    /**
     * @param  string  $eventId  unique per notification; the idempotency key for webhook processing
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly string $gateway,
        public readonly string $eventId,
        public readonly string $orderReference,
        public readonly PaymentStatus $status,
        public readonly ?Money $amount,
        public readonly ?string $providerPaymentId = null,
        public readonly array $raw = [],
    ) {
    }
}
