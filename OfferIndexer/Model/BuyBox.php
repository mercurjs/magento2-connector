<?php

declare(strict_types=1);

namespace Mercur\OfferIndexer\Model;

use Mercur\Connector\Model\Offer;
use Mercur\Connector\Model\OfferRepository;
use Mercur\OfferIndexer\Model\ResourceModel\ProductIndex;

/**
 * What the product page shows: the best candidate and every active seller offer.
 */
class BuyBox
{
    public function __construct(
        private readonly ProductIndex $index,
        private readonly OfferRepository $offers
    ) {
    }

    public function summary(string $sku, int $websiteId): ?array
    {
        return $this->index->get($sku, $websiteId);
    }

    /**
     * @return Offer[]
     */
    public function offers(string $sku, int $websiteId): array
    {
        return $this->offers->getActiveForSku($sku, $websiteId);
    }

    public function bestOffer(string $sku, int $websiteId): ?Offer
    {
        $summary = $this->summary($sku, $websiteId);
        $offerId = $summary['best_offer_id'] ?? null;

        return $offerId ? $this->offers->getById((string) $offerId, $websiteId) : null;
    }
}
