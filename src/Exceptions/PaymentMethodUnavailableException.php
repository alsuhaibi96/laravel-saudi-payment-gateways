<?php

declare(strict_types=1);

namespace Alsuhaibi\SaudiPayments\Exceptions;

final class PaymentMethodUnavailableException extends PaymentException
{
    public static function disabled(string $key): self
    {
        return new self("Payment method [{$key}] is not available.");
    }

    public static function belowMinimum(string $key, int $minHalalas): self
    {
        return new self("Payment method [{$key}] requires at least {$minHalalas} halalas.");
    }

    public static function aboveMaximum(string $key, int $maxHalalas): self
    {
        return new self("Payment method [{$key}] allows at most {$maxHalalas} halalas.");
    }
}
