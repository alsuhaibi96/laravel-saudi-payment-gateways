<?php

declare(strict_types=1);

namespace Alsuhaibi\SaudiPayments\Exceptions;

final class GatewayRequestException extends PaymentException
{
    public static function failed(string $gateway, int $status, string $reason): self
    {
        return new self("[{$gateway}] checkout request failed (HTTP {$status}): {$reason}", $status);
    }

    public static function unexpectedResponse(string $gateway, string $missingField): self
    {
        return new self("[{$gateway}] response is missing [{$missingField}].");
    }
}
