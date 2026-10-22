<?php

declare(strict_types=1);

namespace Mercur\Inventory\Plugin;

use Magento\InventorySales\Model\CheckItemsQuantity;
use Mercur\Inventory\Model\MarketplaceQty;

/**
 * Seller lines are checked against the seller's stock in Mercur, not against
 * the operator's salable quantity.
 */
class SkipMarketplaceSalableCheck
{
    public function __construct(private readonly MarketplaceQty $marketplaceQty)
    {
    }

    public function beforeExecute(CheckItemsQuantity $subject, array $items, int $stockId): array
    {
        $marketplace = $this->marketplaceQty->current();
        if ($marketplace === []) {
            return [$items, $stockId];
        }

        $remaining = [];
        foreach ($items as $sku => $qty) {
            $left = (float) $qty - ($marketplace[(string) $sku] ?? 0.0);
            if ($left > 0) {
                $remaining[$sku] = $left;
            }
        }

        return [$remaining, $stockId];
    }
}
