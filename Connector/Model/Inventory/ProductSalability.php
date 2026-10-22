<?php

declare(strict_types=1);

namespace Mercur\Connector\Model\Inventory;

use Magento\Catalog\Model\Product;
use Mercur\Connector\Model\ResourceModel\Offer\CollectionFactory;
use Mercur\Core\Model\Scope\WebsiteScope;

/**
 * A product is buyable when the operator has stock or any seller offer is
 * available on the current website. The operator's own answer is kept on the
 * product so the buy box can still tell the two apart.
 */
class ProductSalability
{
    public const OWN_SALABLE = 'mercur_own_salable';

    /** @var array<string, bool> */
    private array $cache = [];

    public function __construct(
        private readonly CollectionFactory $offers,
        private readonly WebsiteScope $websites
    ) {
    }

    public function apply(Product $product, bool $ownSalable): bool
    {
        $product->setData(self::OWN_SALABLE, $ownSalable);
        if ($ownSalable || !$product->getSku()) {
            return $ownSalable;
        }

        return $this->hasAvailableOffer((string) $product->getSku(), $this->websites->currentWebsiteId());
    }

    public function hasAvailableOffer(string $sku, int $websiteId): bool
    {
        $key = $websiteId . '|' . $sku;
        if (!isset($this->cache[$key])) {
            $collection = $this->offers->create();
            $collection->addSkuFilter($sku)->addActiveFilter()->addWebsitePrice($websiteId);
            $this->cache[$key] = false;
            foreach ($collection as $offer) {
                if ($offer->isAvailable()) {
                    $this->cache[$key] = true;
                    break;
                }
            }
        }

        return $this->cache[$key];
    }
}
