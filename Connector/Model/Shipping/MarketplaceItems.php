<?php

declare(strict_types=1);

namespace Mercur\Connector\Model\Shipping;

use Magento\Quote\Model\Quote\Item\AbstractItem;

/**
 * Splits rate request items into the operator's own lines and seller lines.
 * Child lines follow their parent.
 */
class MarketplaceItems
{
    public static function isMarketplace(AbstractItem $item): bool
    {
        $owner = $item->getParentItem() ?: $item;

        return (bool) $owner->getData('mercur_offer_id');
    }

    /**
     * @param AbstractItem[] $items
     * @return array{0: AbstractItem[], 1: AbstractItem[]} own items, marketplace items
     */
    public static function split(array $items): array
    {
        $own = [];
        $marketplace = [];
        foreach ($items as $item) {
            if (self::isMarketplace($item)) {
                $marketplace[] = $item;
            } else {
                $own[] = $item;
            }
        }

        return [$own, $marketplace];
    }
}
