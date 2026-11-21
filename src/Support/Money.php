<?php

declare(strict_types=1);

namespace Alsuhaibi\SaudiPayments\Support;

use InvalidArgumentException;

/**
 * An amount in the smallest currency unit (halalas for SAR: 1 SAR = 100 halalas).
 *
 * Integers only: never store or compare money as floats.
 */
final class Money
{
    public function __construct(
        public readonly int $halalas,
        public readonly string $currency = 'SAR',
    ) {
        if ($halalas < 0) {
            throw new InvalidArgumentException('Money cannot be negative.');
        }

        if (! preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new InvalidArgumentException("Invalid ISO 4217 currency code [{$currency}].");
        }
    }

    public static function halalas(int $halalas, string $currency = 'SAR'): self
    {
        return new self($halalas, $currency);
    }

    /**
     * Parse a decimal string such as "149.50" without going through floats.
     */
    public static function fromDecimal(string $amount, string $currency = 'SAR'): self
    {
        $amount = trim($amount);

        if (! preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', $amount, $matches)) {
            throw new InvalidArgumentException("Invalid decimal amount [{$amount}].");
        }

        $whole = (int) $matches[1];
        $fraction = (int) str_pad($matches[2] ?? '0', 2, '0');

        return new self($whole * 100 + $fraction, $currency);
    }

    /**
     * "149.50": the format most provider APIs expect when they don't take minor units.
     */
    public function toDecimal(): string
    {
        return sprintf('%d.%02d', intdiv($this->halalas, 100), $this->halalas % 100);
    }

    public function equals(self $other): bool
    {
        return $this->halalas === $other->halalas && $this->currency === $other->currency;
    }
}
