<?php

declare(strict_types=1);

namespace Mercur\Inventory\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;
use Mercur\Inventory\Model\MarketplaceQty;

/**
 * sales_model_service_quote_submit_before: runs before MSI checks and
 * reserves stock for the new order.
 */
class RegisterMarketplaceQty implements ObserverInterface
{
    public function __construct(private readonly MarketplaceQty $marketplaceQty)
    {
    }

    public function execute(Observer $observer): void
    {
        $order = $observer->getEvent()->getData('order');
        if ($order instanceof Order) {
            $this->marketplaceQty->register((string) $order->getIncrementId(), $order->getAllItems());
        }
    }
}
