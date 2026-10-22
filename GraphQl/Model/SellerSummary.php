<?php

declare(strict_types=1);

namespace Mercur\GraphQl\Model;

use Mercur\Connector\Model\ResourceModel\Seller\CollectionFactory;
use Mercur\Connector\Model\Seller;
use Mercur\Connector\Model\SellerUrl;

/**
 * Builds `MercurSellerSummary` / `MercurSeller` payloads, loading each seller once per request.
 */
class SellerSummary
{
    /** @var array<string, Seller|null> */
    private array $cache = [];

    public function __construct(
        private readonly CollectionFactory $sellers,
        private readonly SellerUrl $sellerUrl
    ) {
    }

    public function byId(string $sellerId): ?Seller
    {
        if (!array_key_exists($sellerId, $this->cache)) {
            $seller = $this->sellers->create()->addFieldToFilter('seller_id', $sellerId)->setPageSize(1)->getFirstItem();
            $this->cache[$sellerId] = $seller instanceof Seller && $seller->getSellerId() !== '' ? $seller : null;
        }

        return $this->cache[$sellerId];
    }

    public function summary(string $sellerId): ?array
    {
        $seller = $this->byId($sellerId);

        return $seller === null ? null : [
            'id' => $seller->getSellerId(),
            'name' => $seller->getName(),
            'handle' => (string) $seller->getData('handle'),
            'url' => $this->sellerUrl->get((string) $seller->getData('handle')),
            'logo' => $seller->getData('logo'),
            'premium' => $seller->isPremium(),
        ];
    }

    public function profile(Seller $seller): array
    {
        return [
            'id' => $seller->getSellerId(),
            'name' => $seller->getName(),
            'handle' => (string) $seller->getData('handle'),
            'url' => $this->sellerUrl->get((string) $seller->getData('handle')),
            'description' => $seller->getData('description'),
            'logo' => $seller->getData('logo'),
            'banner' => $seller->getData('banner'),
            'premium' => $seller->isPremium(),
            'city' => $seller->getData('city'),
            'country_code' => $seller->getData('country_code'),
            'selling_since' => $seller->getData('selling_since'),
        ];
    }
}
