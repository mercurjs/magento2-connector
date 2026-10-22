<?php

declare(strict_types=1);

namespace Mercur\OfferIndexer\Model\Indexer;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable;
use Magento\Framework\App\ResourceConnection;
use Mercur\Connector\Model\Inventory\ProductSalability;
use Mercur\Connector\Model\ResourceModel\Offer\CollectionFactory as OfferCollectionFactory;
use Mercur\Core\Model\Config;
use Mercur\Core\Model\Scope\WebsiteScope;
use Mercur\OfferIndexer\Model\Candidate;
use Mercur\OfferIndexer\Model\ResourceModel\ProductIndex;
use Mercur\OfferIndexer\Model\ResourceModel\SkuResolver;
use Mercur\OfferIndexer\Model\RollupCalculator;

/**
 * Per selling website and SKU, picks the best way to buy among the operator's
 * own stock and the active offers of open sellers, then rolls the children of
 * a configurable up into a row for the parent. SKUs without any seller offer
 * get no row.
 */
class OfferProductIndexer
{
    private const CHUNK = 500;

    public function __construct(
        private readonly OfferCollectionFactory $offers,
        private readonly ProductCollectionFactory $products,
        private readonly ProductIndex $index,
        private readonly RollupCalculator $calculator,
        private readonly Config $config,
        private readonly WebsiteScope $websites,
        private readonly SkuResolver $skus,
        private readonly Configurable $configurable,
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
        $skus = array_values(array_unique(array_filter(array_map('strval', $skus), 'strlen')));
        if ($skus === []) {
            return;
        }

        foreach (array_chunk($skus, self::CHUNK) as $chunk) {
            foreach ($this->websites->all() as $websiteId => $scope) {
                $rule = $this->config->getBestOfferRule($websiteId);
                $offersBySku = $this->loadOffers($chunk, $websiteId);
                $operator = $this->loadOperatorCandidates($chunk, $websiteId, $scope['store_id']);
                $rows = [];
                $empty = [];

                foreach ($chunk as $sku) {
                    $candidates = $offersBySku[$sku] ?? [];
                    if ($candidates === []) {
                        $empty[] = $sku;
                        continue;
                    }
                    if (isset($operator[$sku])) {
                        $candidates[] = $operator[$sku];
                    }
                    $rows[] = ['product_sku' => $sku, 'website_id' => $websiteId]
                        + $this->calculator->summarise($candidates, $rule);
                }

                $this->index->deleteSkus($empty, $websiteId);
                $this->index->replace($rows);
                $this->rollUpParents($chunk, $websiteId);
            }
        }
    }

    /**
     * @param string[] $childSkus
     */
    private function rollUpParents(array $childSkus, int $websiteId): void
    {
        $childIds = array_values($this->skus->productIdsForSkus($childSkus));
        if ($childIds === []) {
            return;
        }
        $parentIds = array_map('intval', $this->configurable->getParentIdsByChild($childIds));
        if ($parentIds === []) {
            return;
        }
        $parentSkus = $this->skus->skusForProductIds($parentIds);

        $rows = [];
        $empty = [];
        foreach ($parentIds as $parentId) {
            $parentSku = $parentSkus[$parentId] ?? null;
            if ($parentSku === null) {
                continue;
            }
            $children = [];
            foreach ($this->configurable->getChildrenIds($parentId) as $group) {
                foreach ($group as $childId) {
                    $children[] = (int) $childId;
                }
            }
            $childRows = $this->index->getMany(array_values($this->skus->skusForProductIds($children)), $websiteId);
            if ($childRows === []) {
                $empty[] = $parentSku;
                continue;
            }
            $rows[] = ['product_sku' => $parentSku, 'website_id' => $websiteId]
                + $this->calculator->parent(array_values($childRows));
        }

        $this->index->deleteSkus($empty, $websiteId);
        $this->index->replace($rows);
    }

    /**
     * @return array<string, Candidate[]>
     */
    private function loadOffers(array $skus, int $websiteId): array
    {
        $collection = $this->offers->create();
        $collection->addSkuFilter($skus)->addActiveFilter()->addWebsitePrice($websiteId)->joinSeller(true);

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
     * The operator's own price and stock on one website, read through the
     * website's default store so price index, status and stock scope apply.
     *
     * @return array<string, Candidate>
     */
    private function loadOperatorCandidates(array $skus, int $websiteId, int $storeId): array
    {
        $collection = $this->products->create();
        $collection->setStoreId($storeId)
            ->addAttributeToSelect(['status', 'price', 'tax_class_id'])
            ->addAttributeToFilter('sku', ['in' => $skus])
            ->addWebsiteFilter($websiteId)
            ->addPriceData(null, $websiteId);

        $result = [];
        /** @var Product $product */
        foreach ($collection as $product) {
            $price = (float) ($product->getData('final_price') ?? $product->getPrice());
            if ($price <= 0) {
                continue;
            }
            $salable = (bool) $product->isSalable();
            if ($product->hasData(ProductSalability::OWN_SALABLE)) {
                $salable = (bool) $product->getData(ProductSalability::OWN_SALABLE);
            }
            $result[(string) $product->getSku()] = Candidate::operator($price, $salable);
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
