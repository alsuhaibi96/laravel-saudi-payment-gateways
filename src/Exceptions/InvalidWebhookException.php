<?php

declare(strict_types=1);

namespace Alsuhaibi\SaudiPayments\Exceptions;

final class InvalidWebhookException extends PaymentException
{
    public static function missingField(string $gateway, string $field): self
    {
        return new self("[{$gateway}] webhook payload is missing [{$field}].");
    }
}
