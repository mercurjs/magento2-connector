<?php

declare(strict_types=1);

namespace Mercur\Connector\Model\Order;

/**
 * Keeps seller lines out of shipments created in Magento: the sellers ship
 * them, and the connector records a shipment for each fulfillment Mercur
 * reports. That recording runs with the lock lifted.
 */
final class ShipmentLock
{
    private bool $lifted = false;

    public function applies(bool $sellerLine, bool $enabled): bool
    {
        return $enabled && $sellerLine && !$this->lifted;
    }

    /**
     * Runs the callable with the lock lifted and restores the previous state afterwards, even on failure.
     */
    public function lift(callable $fn): mixed
    {
        $previous = $this->lifted;
        $this->lifted = true;
        try {
            return $fn();
        } finally {
            $this->lifted = $previous;
        }
    }
}
