<?php

declare(strict_types=1);

namespace Mercur\Connector\Plugin;

use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Quote\Model\Quote\Item\AbstractItem;
use Magento\Shipping\Model\Shipping;
use Mercur\Connector\Model\Carrier\Marketplace;
use Mercur\Connector\Model\Shipping\MarketplaceItems;

/**
 * Sellers ship their own lines and charge through the marketplace shipping
 * total, so the operator's carriers only quote the operator's lines. A cart
 * of seller lines only gets the single marketplace method.
 */
class ExcludeMarketplaceItemsFromRates
{
    public function beforeCollectRates(Shipping $subject, RateRequest $request): array
    {
        $items = $request->getAllItems() ?: [];
        [$own, $marketplace] = MarketplaceItems::split($items);
        if ($marketplace === []) {
            return [$request];
        }

        if ($own === []) {
            $request->setLimitCarrier(Marketplace::CODE);

            return [$request];
        }

        $value = 0.0;
        $valueWithDiscount = 0.0;
        $weight = 0.0;
        $qty = 0.0;
        foreach ($own as $item) {
            if ($item->getParentItem() || $item->getProduct()?->isVirtual()) {
                continue;
            }
            $value += (float) $item->getBaseRowTotal();
            $valueWithDiscount += (float) $item->getBaseRowTotal() - (float) $item->getBaseDiscountAmount();
            $weight += (float) $item->getRowWeight();
            $qty += (float) $item->getQty();
        }

        $request->setAllItems($own);
        $request->setPackageValue($value);
        $request->setPackageValueWithDiscount($valueWithDiscount);
        $request->setPackagePhysicalValue($value);
        $request->setPackageWeight($weight);
        $request->setPackageQty($qty);
        $request->setFreeMethodWeight(min((float) $request->getFreeMethodWeight(), $weight));

        return [$request];
    }
}
