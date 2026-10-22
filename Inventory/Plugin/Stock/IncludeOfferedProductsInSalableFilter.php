<?php

declare(strict_types=1);

namespace Mercur\Inventory\Plugin\Stock;

use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Mercur\Connector\Plugin\Stock\OfferedSkuFilter;

/**
 * MSI counterpart of IncludeOfferedProductsInStockData for
 * Magento\InventoryIndexer\Model\ResourceModel\AddIsInStockFilterToCollection::execute.
 */
class IncludeOfferedProductsInSalableFilter
{
    private const STOCK_ALIAS = 'stock_status_index';

    public function __construct(private readonly OfferedSkuFilter $filter)
    {
    }

    /**
     * @param mixed ...$args $stockId on recent releases
     * @return mixed
     */
    public function aroundExecute(object $subject, callable $proceed, Collection $collection, ...$args)
    {
        $result = $proceed($collection, ...$args);
        if ($this->filter->isEnabled()) {
            $this->filter->widen($collection->getSelect(), self::STOCK_ALIAS);
        }

        return $result;
    }
}
