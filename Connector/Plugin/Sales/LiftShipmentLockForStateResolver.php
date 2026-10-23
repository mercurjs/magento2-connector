<?php

declare(strict_types=1);

namespace Mercur\Connector\Plugin\Sales;

use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order\OrderStateResolverInterface;
use Mercur\Connector\Model\Order\ShipmentLock;

/**
 * Same as LiftShipmentLockForStateHandler for the state resolver used by
 * invoices, shipments and credit memos.
 */
class LiftShipmentLockForStateResolver
{
    public function __construct(private readonly ShipmentLock $lock)
    {
    }

    public function aroundGetStateForOrder(
        OrderStateResolverInterface $subject,
        callable $proceed,
        OrderInterface $order,
        array $arguments = []
    ): string {
        return $this->lock->lift(static fn () => $proceed($order, $arguments));
    }
}
