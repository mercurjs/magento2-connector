<?php

declare(strict_types=1);

namespace Mercur\Catalog\Model\Product;

use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
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
        private readonly WebsiteScope $websites,
        private readonly Configurable $configurableType
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

        $childIds = [];
        foreach ($imported as $product) {
            $sku = (string) $product->getSku();
            $childIds[] = (int) $product->getId();
            $available = false;
            foreach ($websiteIds as $websiteId) {
                if ($this->salability->hasAvailableOffer($sku, $websiteId)) {
                    $available = true;
                    break;
                }
            }
            $this->setInStock($sku, $available);
        }
        $this->updateParents($childIds);
    }

    /**
     * A configurable parent is gated by its own flag, which nothing else
     * maintains for imported products: it is in stock while any child is.
     *
     * @param int[] $childIds
     */
    private function updateParents(array $childIds): void
    {
        $parentIds = array_unique(array_map('intval', $this->configurableType->getParentIdsByChild($childIds)));
        if ($parentIds === []) {
            return;
        }
        $parents = $this->productCollection->create()->addIdFilter($parentIds);
        foreach ($parents as $parent) {
            $available = false;
            foreach ($this->configurableType->getChildrenIds((int) $parent->getId()) as $group) {
                foreach ($group as $childId) {
                    if ((bool) $this->stockRegistry->getStockItem((int) $childId)->getIsInStock()) {
                        $available = true;
                        break 2;
                    }
                }
            }
            $this->setInStock((string) $parent->getSku(), $available);
        }
    }

    private function setInStock(string $sku, bool $available): void
    {
        $stockItem = $this->stockRegistry->getStockItemBySku($sku);
        if ((bool) $stockItem->getIsInStock() === $available) {
            return;
        }
        $stockItem->setIsInStock($available);
        $this->stockRegistry->updateStockItemBySku($sku, $stockItem);
    }
}
