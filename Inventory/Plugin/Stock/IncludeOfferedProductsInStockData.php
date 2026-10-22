<?php

declare(strict_types=1);

namespace Mercur\Inventory\Plugin\Stock;

use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Mercur\Connector\Plugin\Stock\OfferedSkuFilter;

/**
 * MSI: after Magento joins the stock index and filters on is_salable, the
 * join is widened to a LEFT join and SKUs with an available seller offer are
 * accepted as well. The signatures of the two MSI classes vary between
 * releases, so the extra arguments are passed through untouched.
 */
class IncludeOfferedProductsInStockData
{
    private const STOCK_ALIAS = 'stock_status_index';

    public function __construct(private readonly OfferedSkuFilter $filter)
    {
    }

    /**
     * Magento\InventoryIndexer\Model\ResourceModel\AddStockDataToCollection::execute
     *
     * @param mixed ...$args $isFilterInStock and, on recent releases, $stockId
     * @return Collection
     */
    public function aroundExecute(object $subject, callable $proceed, Collection $collection, ...$args)
    {
        $result = $proceed($collection, ...$args);
        $isFilterInStock = (bool) ($args[0] ?? false);
        if ($isFilterInStock && $this->filter->isEnabled()) {
            $this->filter->widen($collection->getSelect(), self::STOCK_ALIAS);
        }

        return $result;
    }
}
