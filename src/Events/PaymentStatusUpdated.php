<?php

declare(strict_types=1);

namespace Alsuhaibi\SaudiPayments\Events;

use Alsuhaibi\SaudiPayments\Data\PaymentResult;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired exactly once per verified webhook event. Listen to it to update your order.
 *
 * Your listener should still compare $result->amount with the order total and
 * ignore transitions out of a final state (e.g. paid -> pending).
 */
final class PaymentStatusUpdated
{
    use Dispatchable;

    public function __construct(public readonly PaymentResult $result)
    {
    }
}
