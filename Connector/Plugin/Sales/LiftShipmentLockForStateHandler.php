<?php

declare(strict_types=1);

namespace Mercur\Connector\Plugin\Sales;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order\Handler\State;
use Mercur\Connector\Model\Order\ShipmentLock;

/**
 * Order state is derived from Order::canShip(). With the seller lines
 * locked an invoiced order would count as complete while the sellers have
 * not shipped yet, so the state is resolved with the lock lifted.
 */
class LiftShipmentLockForStateHandler
{
    public function __construct(private readonly ShipmentLock $lock)
    {
    }

    public function aroundCheck(State $subject, callable $proceed, Order $order): State
    {
        return $this->lock->lift(static fn () => $proceed($order));
    }
}
