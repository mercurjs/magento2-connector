<?php

declare(strict_types=1);

namespace Mercur\OfferIndexer\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Indexer\IndexerRegistry;
use Mercur\OfferIndexer\Model\Indexer\Action;
use Mercur\OfferIndexer\Model\Indexer\OfferProductIndexer;
use Mercur\OfferIndexer\Model\ResourceModel\SkuResolver;

/**
 * The offer tables are not mview subscriptions, so an import drives the
 * indexer itself: through the registry when it runs on schedule, directly
 * otherwise.
 */
class ReindexAfterImport implements ObserverInterface
{
    public function __construct(
        private readonly OfferProductIndexer $indexer,
        private readonly IndexerRegistry $registry,
        private readonly SkuResolver $skus
    ) {
    }

    public function execute(Observer $observer): void
    {
        $indexer = $this->registry->get(Action::INDEXER_ID);
        if ($observer->getEvent()->getData('full')) {
            if ($indexer->isScheduled()) {
                $indexer->invalidate();
            } else {
                $this->indexer->reindexAll();
            }

            return;
        }

        $skus = $observer->getEvent()->getData('skus');
        $skus = is_array($skus) ? array_map('strval', $skus) : [];
        if (!$indexer->isScheduled()) {
            $this->indexer->reindexSkus($skus);

            return;
        }
        $ids = array_values($this->skus->productIdsForSkus($skus));
        if ($ids !== []) {
            $indexer->reindexList($ids);
        }
    }
}
