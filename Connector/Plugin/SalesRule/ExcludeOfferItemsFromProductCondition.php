<?php

declare(strict_types=1);

namespace Mercur\Connector\Plugin\SalesRule;

use Magento\Framework\Model\AbstractModel;
use Magento\SalesRule\Model\Rule\Condition\Product;

/**
 * A seller line never satisfies a product condition, so neither a rule's
 * conditions nor its actions pick it up.
 */
class ExcludeOfferItemsFromProductCondition
{
    public function aroundValidate(Product $subject, callable $proceed, AbstractModel $model): bool
    {
        if (OfferItem::is($model)) {
            return false;
        }

        return (bool) $proceed($model);
    }
}
