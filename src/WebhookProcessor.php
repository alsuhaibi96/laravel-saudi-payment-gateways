<?php

declare(strict_types=1);

namespace Alsuhaibi\SaudiPayments;

use Alsuhaibi\SaudiPayments\Data\PaymentResult;
use Alsuhaibi\SaudiPayments\Events\PaymentStatusUpdated;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionInterface;

/**
 * Records each webhook event once and dispatches PaymentStatusUpdated only for new events.
 *
 * Providers retry webhooks (timeouts, 5xx), and may send the same event several times.
 * The unique (gateway, event_id) index makes the insert the lock: whoever inserts first wins.
 */
final class WebhookProcessor
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly Dispatcher $events,
    ) {
    }

    /**
     * @return bool true if the event was new and dispatched, false if it was a duplicate
     */
    public function process(PaymentResult $result): bool
    {
        return $this->db->transaction(function () use ($result): bool {
            $inserted = $this->db->table('payment_webhook_events')->insertOrIgnore([
                'gateway' => $result->gateway,
                'event_id' => $result->eventId,
                'order_reference' => $result->orderReference,
                'status' => $result->status->value,
                'amount_halalas' => $result->amount?->halalas,
                'payload' => json_encode($result->raw, JSON_THROW_ON_ERROR),
                'created_at' => now(),
            ]);

            if ($inserted === 0) {
                return false;
            }

            // Inside the transaction: if a synchronous listener throws, the event row is rolled
            // back too and the provider's retry gets a second chance.
            $this->events->dispatch(new PaymentStatusUpdated($result));

            return true;
        });
    }
}
