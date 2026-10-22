<?php

declare(strict_types=1);

namespace Mercur\Catalog\Model\Product;

use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Mercur\Connector\Model\Inventory\ProductSalability;
use Mercur\Core\Model\Scope\WebsiteScope;

/**
 * Imported seller products have no stock of their own: they are in stock
 * exactly while some website has an available offer for them, so listings
 * that hide out-of-stock products hide them too.
 */
class OfferStockUpdater
{
    public function __construct(
        private readonly CollectionFactory $productCollection,
        private readonly StockRegistryInterface $stockRegistry,
        private readonly ProductSalability $salability,
        private readonly WebsiteScope $websites
    ) {
    }

    /**
     * @param string[] $skus
     */
    public function update(array $skus): void
    {
        if ($skus === []) {
            return;
        }
        $imported = $this->productCollection->create()
            ->addAttributeToFilter('sku', ['in' => $skus])
            ->addAttributeToFilter('mercur_product', 1)
            ->addAttributeToFilter('type_id', 'simple');
        $websiteIds = array_keys($this->websites->all());

        foreach ($imported as $product) {
            $sku = (string) $product->getSku();
            $available = false;
            foreach ($websiteIds as $websiteId) {
                if ($this->salability->hasAvailableOffer($sku, $websiteId)) {
                    $available = true;
                    break;
                }
            }
            $stockItem = $this->stockRegistry->getStockItemBySku($sku);
            if ((bool) $stockItem->getIsInStock() === $available) {
                continue;
            }
            $stockItem->setIsInStock($available);
            $this->stockRegistry->updateStockItemBySku($sku, $stockItem);
        }
    }
}
