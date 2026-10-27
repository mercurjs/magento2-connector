<?php

declare(strict_types=1);

namespace Mercur\OfferIndexer\Model;

use Magento\Catalog\Model\Product;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\Indexer\CacheContext;
use Magento\Framework\Indexer\CacheContextFactory;

/**
 * Product pages render the buy box from the offer index, so a changed index
 * row has to evict the cached page. Magento's indexer cache plugin only covers
 * runs through the indexer framework; offer and seller syncs reindex directly.
 */
class ProductCacheCleaner
{
    public function __construct(
        private readonly CacheContextFactory $contextFactory,
        private readonly EventManager $eventManager,
        private readonly CacheInterface $cache
    ) {
    }

    /**
     * @param int[] $productIds
     */
    public function cleanProducts(array $productIds): void
    {
        $productIds = array_values(array_unique(array_map('intval', $productIds)));
        if ($productIds === []) {
            return;
        }
        $context = $this->contextFactory->create();
        $context->registerEntities(Product::CACHE_TAG, $productIds);
        $this->flush($context);
    }

    public function cleanAll(): void
    {
        $context = $this->contextFactory->create();
        $context->registerTags([Product::CACHE_TAG]);
        $this->flush($context);
    }

    private function flush(CacheContext $context): void
    {
        $this->eventManager->dispatch('clean_cache_by_tags', ['object' => $context]);
        $this->cache->clean($context->getIdentities());
    }
}
