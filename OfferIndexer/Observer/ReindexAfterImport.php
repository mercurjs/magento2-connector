<?php

declare(strict_types=1);

namespace Mercur\OfferIndexer\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Mercur\OfferIndexer\Model\Indexer\OfferProductIndexer;

class ReindexAfterImport implements ObserverInterface
{
    public function __construct(private readonly OfferProductIndexer $indexer)
    {
    }

    public function execute(Observer $observer): void
    {
        if ($observer->getEvent()->getData('full')) {
            $this->indexer->reindexAll();

            return;
        }

        $skus = $observer->getEvent()->getData('skus');
        $this->indexer->reindexSkus(is_array($skus) ? $skus : []);
    }
}
