<?php

declare(strict_types=1);

namespace Mercur\Connector\Plugin;

use Magento\CatalogInventory\Observer\SubtractQuoteInventoryObserver;
use Magento\Framework\Event\Observer;

class SkipStockDeductionForOffers
{
    public function aroundExecute(SubtractQuoteInventoryObserver $subject, callable $proceed, Observer $observer): mixed
    {
        $quote = $observer->getEvent()->getData('quote');
        if (!$quote) {
            return $proceed($observer);
        }

        $marketplace = [];
        foreach ($quote->getAllItems() as $item) {
            if ($item->getData('mercur_offer_id')) {
                $marketplace[] = $item;
                $item->setData('mercur_skip_stock', true);
            }
        }
        if ($marketplace === []) {
            return $proceed($observer);
        }

        $all = $quote->getAllItems();
        $quote->setItems(array_values(array_filter($all, static fn ($i) => !$i->getData('mercur_skip_stock'))));
        try {
            return $proceed($observer);
        } finally {
            $quote->setItems($all);
        }
    }
}
