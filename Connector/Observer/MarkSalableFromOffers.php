<?php

declare(strict_types=1);

namespace Mercur\Connector\Observer;

use Magento\Catalog\Model\Product;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Mercur\Connector\Model\Inventory\ProductSalability;
use Mercur\Core\Model\Config;

/**
 * catalog_product_is_salable_after: a SKU the operator has run out of stays
 * buyable while a seller offers it.
 */
class MarkSalableFromOffers implements ObserverInterface
{
    public function __construct(
        private readonly ProductSalability $salability,
        private readonly Config $config
    ) {
    }

    public function execute(Observer $observer): void
    {
        $product = $observer->getEvent()->getData('product');
        $result = $observer->getEvent()->getData('salable');
        if (!$product instanceof Product || !$result || !$this->config->isEnabled((int) $product->getStoreId())) {
            return;
        }
        $result->setData('is_salable', $this->salability->apply($product, (bool) $result->getData('is_salable')));
    }
}
