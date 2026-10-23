<?php

declare(strict_types=1);

namespace Mercur\Connector\Plugin\Sales;

use Magento\Sales\Model\Order\Item;
use Mercur\Connector\Model\Order\ShipmentLock;
use Mercur\Core\Model\Config;

/**
 * Reports seller lines as locked for shipping. locked_do_ship is what every
 * shipping surface checks (Order::canShip, the Ship form, ShipOrder's
 * validation, REST), while qty_to_ship also drives cancellation and must
 * stay untouched.
 */
class LockSellerShipping
{
    public function __construct(
        private readonly ShipmentLock $lock,
        private readonly Config $config
    ) {
    }

    /**
     * @param int|null $result
     * @return int|null
     */
    public function afterGetLockedDoShip(Item $subject, $result)
    {
        if ($result) {
            return $result;
        }
        $owner = $subject->getParentItem() ?: $subject;
        $enabled = $this->config->lockSellerShipping((int) $subject->getStoreId());

        return $this->lock->applies((bool) $owner->getData('mercur_offer_id'), $enabled) ? 1 : $result;
    }
}
