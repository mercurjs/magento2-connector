<?php

declare(strict_types=1);

namespace Mercur\OfferIndexer\Model\Indexer;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\StoreManagerInterface;
use Mercur\Connector\Model\ResourceModel\Offer\CollectionFactory as OfferCollectionFactory;
use Mercur\Core\Model\Config;
use Mercur\OfferIndexer\Model\BestOfferSelector;
use Mercur\OfferIndexer\Model\Candidate;
use Mercur\OfferIndexer\Model\ResourceModel\ProductIndex;

/**
 * Per website and SKU, picks the best way to buy among the operator's own stock
 * and the active seller offers, and records min and max for listing badges.
 */
class OfferProductIndexer
{
    public function __construct(
        private readonly OfferCollectionFactory $offers,
        private readonly ProductIndex $index,
        private readonly BestOfferSelector $selector,
        private readonly Config $config,
        private readonly StoreManagerInterface $storeManager,
        private readonly ProductRepositoryInterface $products,
        private readonly SearchCriteriaBuilder $criteria,
        private readonly ResourceConnection $resource
    ) {
    }

    public function reindexAll(): void
    {
        $this->index->truncate();
        $this->reindexSkus($this->allOfferSkus());
    }

    /**
     * @param string[] $skus
     */
    public function reindexSkus(array $skus): void
    {
        if ($skus === []) {
            return;
        }

        foreach (array_chunk($skus, 500) as $chunk) {
            $offersBySku = $this->loadOffers($chunk);
            $operator = $this->loadOperatorCandidates($chunk);

            foreach ($this->storeManager->getWebsites() as $website) {
                $websiteId = (int) $website->getId();
                $rule = $this->config->getBestOfferRule($websiteId);
                $rows = [];
                $empty = [];

                foreach ($chunk as $sku) {
                    $candidates = $offersBySku[$sku] ?? [];
                    $op = $operator[$sku] ?? null;
                    if ($op !== null) {
                        $candidates[] = $op;
                    }
                    if ($candidates === []) {
                        $empty[] = $sku;
                        continue;
                    }

                    $best = $this->selector->select($candidates, $rule);
                    $prices = array_map(static fn (Candidate $c) => $c->price, array_filter($candidates, static fn (Candidate $c) => $c->available));
                    $rows[] = [
                        'product_sku' => $sku,
                        'website_id' => $websiteId,
                        'best_offer_id' => $best?->offerId,
                        'best_seller_id' => $best?->sellerId,
                        'best_price' => $best?->price,
                        'min_price' => $prices === [] ? null : min($prices),
                        'max_price' => $prices === [] ? null : max($prices),
                        'offer_count' => count($offersBySku[$sku] ?? []),
                        'operator_available' => (int) ($op?->available ?? false),
                    ];
                }

                $this->index->deleteSkus($empty, $websiteId);
                $this->index->replace($rows);
            }
        }
    }

    /**
     * @return array<string, Candidate[]>
     */
    private function loadOffers(array $skus): array
    {
        $collection = $this->offers->create();
        $collection->addSkuFilter($skus)->addActiveFilter()->joinSeller();

        $result = [];
        foreach ($collection as $offer) {
            $result[$offer->getProductSku()][] = new Candidate(
                $offer->getOfferId(),
                $offer->getSellerId(),
                $offer->getPrice(),
                (bool) $offer->getData('seller_premium'),
                $offer->isAvailable()
            );
        }

        return $result;
    }

    /**
     * @return array<string, Candidate>
     */
    private function loadOperatorCandidates(array $skus): array
    {
        $criteria = $this->criteria->addFilter('sku', $skus, 'in')->create();
        $result = [];
        foreach ($this->products->getList($criteria)->getItems() as $product) {
            $price = (float) $product->getPrice();
            if ($price <= 0) {
                continue;
            }
            $salable = (bool) $product->isSalable();
            $result[$product->getSku()] = Candidate::operator($price, $salable);
        }

        return $result;
    }

    /**
     * @return string[]
     */
    private function allOfferSkus(): array
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()->distinct()->from($this->resource->getTableName('mercur_offer'), ['product_sku']);

        return array_map('strval', $connection->fetchCol($select));
    }
}
