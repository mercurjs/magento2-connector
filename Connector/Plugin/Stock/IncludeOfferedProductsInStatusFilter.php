<?php

declare(strict_types=1);

namespace Mercur\Connector\Plugin\Stock;

use Magento\CatalogInventory\Api\StockConfigurationInterface;
use Magento\CatalogInventory\Model\ResourceModel\Stock\Status;
use Magento\CatalogInventory\Model\Stock;

/**
 * Legacy stock (no MSI): the stock status join becomes a LEFT join and the
 * in-stock filter also accepts SKUs with an available seller offer.
 */
class IncludeOfferedProductsInStatusFilter
{
    public function __construct(
        private readonly OfferedSkuFilter $filter,
        private readonly StockConfigurationInterface $stockConfiguration
    ) {
    }

    /**
     * @param \Magento\Catalog\Model\ResourceModel\Product\Collection $collection
     * @param bool $isFilterInStock
     * @return \Magento\Catalog\Model\ResourceModel\Product\Collection
     */
    public function aroundAddStockDataToCollection(Status $subject, callable $proceed, $collection, $isFilterInStock)
    {
        if (!$isFilterInStock || !$this->filter->isEnabled()) {
            return $proceed($collection, $isFilterInStock);
        }

        $collection->getSelect()->joinLeft(
            ['stock_status_index' => $subject->getMainTable()],
            $this->joinCondition($subject),
            ['is_salable' => 'stock_status']
        );
        $collection->getSelect()->where($this->inStockOrOffered($subject, $collection->getSelect()));

        return $collection;
    }

    /**
     * @param \Magento\Catalog\Model\ResourceModel\Product\Collection $collection
     * @return Status
     */
    public function aroundAddIsInStockFilterToCollection(Status $subject, callable $proceed, $collection)
    {
        if (!$this->filter->isEnabled()) {
            return $proceed($collection);
        }

        $collection->getSelect()->joinLeft(
            ['stock_status_index' => $subject->getMainTable()],
            $this->joinCondition($subject),
            []
        )->where($this->inStockOrOffered($subject, $collection->getSelect()));

        return $subject;
    }

    private function joinCondition(Status $subject): string
    {
        $connection = $subject->getConnection();

        return $connection->quoteInto(
            'e.entity_id = stock_status_index.product_id AND stock_status_index.website_id = ?',
            $this->stockConfiguration->getDefaultScopeId()
        ) . $connection->quoteInto(' AND stock_status_index.stock_id = ?', Stock::DEFAULT_STOCK_ID);
    }

    private function inStockOrOffered(Status $subject, \Magento\Framework\DB\Select $select): string
    {
        return $subject->getConnection()->quoteInto('stock_status_index.stock_status = ?', Stock\Status::STATUS_IN_STOCK)
            . ' OR ' . $this->filter->skuCondition($select);
    }
}
