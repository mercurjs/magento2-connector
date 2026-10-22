<?php

declare(strict_types=1);

namespace Mercur\Connector\Model;

use Mercur\Connector\Model\ResourceModel\Offer\CollectionFactory;

/**
 * Offers carry a price per website, so every read names the website it sells on.
 */
class OfferRepository
{
    public function __construct(
        private readonly CollectionFactory $collectionFactory
    ) {
    }

    public function getById(string $offerId, int $websiteId): ?Offer
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('main_table.offer_id', $offerId)
            ->addWebsitePrice($websiteId)
            ->joinSeller()
            ->setPageSize(1);
        $offer = $collection->getFirstItem();

        return $offer instanceof Offer && $offer->getOfferId() !== '' ? $offer : null;
    }

    /**
     * @return Offer[] cheapest first
     */
    public function getActiveForSku(string $sku, int $websiteId): array
    {
        return $this->getActiveForSkus([$sku], $websiteId);
    }

    /**
     * @param string[] $skus
     * @return Offer[] cheapest first
     */
    public function getActiveForSkus(array $skus, int $websiteId): array
    {
        if ($skus === []) {
            return [];
        }
        $collection = $this->collectionFactory->create();
        $collection->addSkuFilter($skus)
            ->addActiveFilter()
            ->addWebsitePrice($websiteId)
            ->joinSeller()
            ->setOrder('offer_price.price', 'ASC');

        return array_values($collection->getItems());
    }

    /**
     * @return Offer[]
     */
    public function getActiveForSeller(string $sellerId, int $websiteId): array
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('main_table.seller_id', $sellerId)
            ->addActiveFilter()
            ->addWebsitePrice($websiteId)
            ->joinSeller();

        return array_values($collection->getItems());
    }

    /**
     * @param string[] $ids
     * @return array<string, Offer> keyed by offer id
     */
    public function getByIds(array $ids, int $websiteId): array
    {
        if ($ids === []) {
            return [];
        }
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('main_table.offer_id', ['in' => $ids])
            ->addWebsitePrice($websiteId)
            ->joinSeller();
        $result = [];
        foreach ($collection as $offer) {
            $result[$offer->getOfferId()] = $offer;
        }

        return $result;
    }
}
