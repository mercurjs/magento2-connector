<?php

declare(strict_types=1);

namespace Mercur\Connector\Plugin\SalesRule;

use Magento\Framework\Model\AbstractModel;
use Magento\Quote\Model\Quote\Item\AbstractItem;

final class OfferItem
{
    public static function is(?AbstractModel $model): bool
    {
        if (!$model instanceof AbstractItem) {
            return false;
        }
        if ($model->getData('mercur_offer_id')) {
            return true;
        }
        $parent = $model->getParentItem();

        return $parent instanceof AbstractItem && (bool) $parent->getData('mercur_offer_id');
    }
}
