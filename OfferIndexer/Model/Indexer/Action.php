<?php

declare(strict_types=1);

namespace Mercur\OfferIndexer\Model\Indexer;

use Magento\Framework\Indexer\ActionInterface as IndexerActionInterface;
use Magento\Framework\Mview\ActionInterface as MviewActionInterface;
use Mercur\OfferIndexer\Model\ResourceModel\SkuResolver;

class Action implements IndexerActionInterface, MviewActionInterface
{
    public const INDEXER_ID = 'mercur_offer_product';

    public function __construct(
        private readonly OfferProductIndexer $indexer,
        private readonly SkuResolver $skus
    ) {
    }

    public function executeFull(): void
    {
        $this->indexer->reindexAll();
    }

    public function executeList(array $ids): void
    {
        $this->indexer->reindexSkus($this->skus->skusForProductIds(array_map('intval', $ids)));
    }

    public function executeRow($id): void
    {
        $this->executeList([$id]);
    }

    public function execute($ids): void
    {
        $this->executeList(is_array($ids) ? $ids : [$ids]);
    }
}
