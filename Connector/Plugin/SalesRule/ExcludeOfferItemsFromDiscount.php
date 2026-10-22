<?php

declare(strict_types=1);

namespace Mercur\Connector\Plugin\SalesRule;

use Magento\Quote\Model\Quote\Item\AbstractItem;
use Magento\SalesRule\Model\Validator;

/**
 * Seller lines are priced by the seller; the operator's cart price rules never discount them.
 */
class ExcludeOfferItemsFromDiscount
{
    public function afterCanApplyDiscount(Validator $subject, bool $result, AbstractItem $item): bool
    {
        return $result && !OfferItem::is($item);
    }
}
