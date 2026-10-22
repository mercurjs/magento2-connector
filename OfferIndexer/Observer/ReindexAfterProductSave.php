<?php

declare(strict_types=1);

namespace Mercur\OfferIndexer\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Indexer\IndexerRegistry;
use Mercur\OfferIndexer\Model\Indexer\Action;
use Mercur\OfferIndexer\Model\Indexer\OfferProductIndexer;

/**
 * The operator's own price and stock are candidates too, so a product save
 * re-evaluates the best offer for that SKU. On schedule the
 * catalog_product_entity subscription already picks the save up.
 */
class ReindexAfterProductSave implements ObserverInterface
{
    public function __construct(
        private readonly OfferProductIndexer $indexer,
        private readonly IndexerRegistry $registry
    ) {
    }

    public function execute(Observer $observer): void
    {
        $product = $observer->getEvent()->getData('product');
        if (!$product || !$product->getSku() || $this->registry->get(Action::INDEXER_ID)->isScheduled()) {
            return;
        }
        $this->indexer->reindexSkus([(string) $product->getSku()]);
    }
}
