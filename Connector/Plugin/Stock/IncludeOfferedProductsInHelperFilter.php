<?php

declare(strict_types=1);

namespace Mercur\Connector\Plugin\Stock;

use Magento\CatalogInventory\Helper\Stock as StockHelper;
use Magento\CatalogInventory\Model\Configuration;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Legacy stock: the related/up-sell/cross-sell filter joins the stock item
 * LEFT and lets SKUs with an available seller offer through.
 */
class IncludeOfferedProductsInHelperFilter
{
    private const ALIAS = 'inventory_in_stock';

    public function __construct(
        private readonly OfferedSkuFilter $filter,
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    /**
     * @param \Magento\Catalog\Model\ResourceModel\Product\Link\Product\Collection $collection
     */
    public function aroundAddInStockFilterToCollection(StockHelper $subject, callable $proceed, $collection): void
    {
        if (!$this->filter->isEnabled()) {
            $proceed($collection);

            return;
        }

        $manageStock = $this->scopeConfig->getValue(Configuration::XML_PATH_MANAGE_STOCK, ScopeInterface::SCOPE_STORE);
        $cond = [
            '{{table}}.use_config_manage_stock = 0 AND {{table}}.manage_stock=1 AND {{table}}.is_in_stock=1',
            '{{table}}.use_config_manage_stock = 0 AND {{table}}.manage_stock=0',
        ];
        $cond[] = $manageStock
            ? '{{table}}.use_config_manage_stock = 1 AND {{table}}.is_in_stock=1'
            : '{{table}}.use_config_manage_stock = 1';

        $collection->joinField(
            self::ALIAS,
            'cataloginventory_stock_item',
            'is_in_stock',
            'product_id=entity_id',
            '(' . implode(') OR (', $cond) . ')',
            'left'
        );
        // joinField prefixes the alias with "at_" when naming the joined table.
        $collection->getSelect()->where(
            'at_' . self::ALIAS . '.product_id IS NOT NULL OR ' . $this->filter->skuCondition($collection->getSelect())
        );
    }
}
