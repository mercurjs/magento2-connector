<?php

declare(strict_types=1);

namespace Mercur\Connector\Plugin;

use Magento\Quote\Model\Quote;

/**
 * After a guest cart is merged into a customer's, neither Mercur cart matches
 * the new line set. Dropping the references makes the next totals collection
 * start a fresh shadow cart.
 */
class ResetShadowCartOnMerge
{
    public function afterMerge(Quote $subject, Quote $result, Quote $source): Quote
    {
        $subject->setData('mercur_cart_id', null);
        $subject->setData('mercur_shipping_methods', null);
        $subject->setData('mercur_sync_hash', null);
        foreach ($subject->getAllItems() as $item) {
            if ($item->getData('mercur_line_item_id')) {
                $item->setData('mercur_line_item_id', null);
            }
        }

        return $result;
    }
}
