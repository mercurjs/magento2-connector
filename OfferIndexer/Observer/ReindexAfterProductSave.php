<?php

declare(strict_types=1);

namespace Mercur\OfferIndexer\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Mercur\OfferIndexer\Model\Indexer\OfferProductIndexer;

/**
 * The operator's own price and stock are candidates too, so a product save
 * re-evaluates the best offer for that SKU.
 */
class ReindexAfterProductSave implements ObserverInterface
{
    public function __construct(private readonly OfferProductIndexer $indexer)
    {
    }

    public function execute(Observer $observer): void
    {
        $product = $observer->getEvent()->getData('product');
        if ($product && $product->getSku()) {
            $this->indexer->reindexSkus([(string) $product->getSku()]);
        }
    }
}
