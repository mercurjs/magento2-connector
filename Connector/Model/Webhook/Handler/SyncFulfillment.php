<?php

declare(strict_types=1);

namespace Mercur\Connector\Model\Webhook\Handler;

use Mercur\Connector\Model\Fulfillment\FulfillmentSync;
use Mercur\Connector\Model\Webhook\HandlerInterface;

/**
 * Fulfillment, shipment, delivery and return events all resolve to "re-read
 * this seller order": the payload names the order (`order_id`, or `id` on the
 * older order events).
 */
class SyncFulfillment implements HandlerInterface
{
    private const COMMENTS = [
        'order.fulfillment_canceled' => 'A seller canceled a fulfillment of seller order %1.',
        'delivery.created' => 'Seller order %1 was delivered.',
        'order.return_requested' => 'A return was requested for seller order %1.',
        'order.return_received' => 'The seller received a return for seller order %1.',
    ];

    public function __construct(private readonly FulfillmentSync $sync)
    {
    }

    public function handle(string $event, array $data): void
    {
        $sellerOrderId = (string) ($data['order_id'] ?? $data['id'] ?? '');
        if ($sellerOrderId === '') {
            return;
        }
        $comment = isset(self::COMMENTS[$event]) ? (string) __(self::COMMENTS[$event], $sellerOrderId) : null;
        $this->sync->sync($sellerOrderId, $comment);
    }
}
