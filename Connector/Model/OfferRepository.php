<?php

declare(strict_types=1);

namespace Mercur\Connector\Model;

use Mercur\Connector\Model\ResourceModel\Offer as OfferResource;
use Mercur\Connector\Model\ResourceModel\Offer\CollectionFactory;

class OfferRepository
{
    public function __construct(
        private readonly OfferFactory $offerFactory,
        private readonly OfferResource $resource,
        private readonly CollectionFactory $collectionFactory
    ) {
    }

    public function getById(string $offerId): ?Offer
    {
        $offer = $this->offerFactory->create();
        $this->resource->load($offer, $offerId);

        return $offer->getOfferId() === '' ? null : $offer;
    }

    /**
     * @return Offer[]
     */
    public function getActiveForSku(string $sku): array
    {
        $collection = $this->collectionFactory->create();
        $collection->addSkuFilter($sku)->addActiveFilter()->joinSeller()->setOrder('price', 'ASC');

        return array_values($collection->getItems());
    }

    /**
     * @param string[] $ids
     * @return array<string, Offer> keyed by offer id
     */
    public function getByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('offer_id', ['in' => $ids])->joinSeller();
        $result = [];
        foreach ($collection as $offer) {
            $result[$offer->getOfferId()] = $offer;
        }

        return $result;
    }
}
