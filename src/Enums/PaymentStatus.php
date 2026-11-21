<?php

declare(strict_types=1);

namespace Alsuhaibi\SaudiPayments\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Authorized = 'authorized';
    case Paid = 'paid';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';

    public function isFinal(): bool
    {
        return match ($this) {
            self::Paid, self::Failed, self::Cancelled, self::Refunded => true,
            self::Pending, self::Authorized => false,
        };
    }
}
