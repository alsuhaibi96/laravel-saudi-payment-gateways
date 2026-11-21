<?php

declare(strict_types=1);

namespace Alsuhaibi\SaudiPayments\Exceptions;

final class UnsupportedGatewayException extends PaymentException
{
    public static function forKey(string $key): self
    {
        return new self("Payment gateway [{$key}] is not configured.");
    }
}
