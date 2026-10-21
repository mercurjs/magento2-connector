<?php

declare(strict_types=1);

namespace Mercur\Connector\Plugin;

use Magento\CatalogInventory\Model\Quote\Item\QuantityValidator;
use Magento\Framework\Event\Observer;

/**
 * The seller owns the stock behind a marketplace line, so Magento's own stock
 * check must not reject or reserve it.
 */
class SkipStockValidationForOffers
{
    public function aroundValidate(QuantityValidator $subject, callable $proceed, Observer $observer): void
    {
        $item = $observer->getEvent()->getData('item');
        if ($item && $item->getData('mercur_offer_id')) {
            return;
        }

        $proceed($observer);
    }
}
