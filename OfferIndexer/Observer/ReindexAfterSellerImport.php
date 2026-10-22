<?php

declare(strict_types=1);

namespace Mercur\OfferIndexer\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Indexer\IndexerRegistry;
use Mercur\Connector\Model\ResourceModel\Offer as OfferResource;
use Mercur\OfferIndexer\Model\Indexer\Action;
use Mercur\OfferIndexer\Model\Indexer\OfferProductIndexer;
use Mercur\OfferIndexer\Model\ResourceModel\SkuResolver;

/**
 * A seller leaving or re-entering `open` changes which offers count, so the
 * SKUs of every touched seller are re-evaluated.
 */
class ReindexAfterSellerImport implements ObserverInterface
{
    public function __construct(
        private readonly OfferProductIndexer $indexer,
        private readonly IndexerRegistry $registry,
        private readonly OfferResource $offers,
        private readonly SkuResolver $skus
    ) {
    }

    public function execute(Observer $observer): void
    {
        $event = $observer->getEvent();
        $sellerIds = array_merge(
            array_column(is_array($event->getData('sellers')) ? $event->getData('sellers') : [], 'seller_id'),
            is_array($event->getData('deleted')) ? $event->getData('deleted') : []
        );
        $skus = $this->offers->skusForSellers(array_map('strval', $sellerIds));
        if ($skus === []) {
            return;
        }

        $indexer = $this->registry->get(Action::INDEXER_ID);
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
