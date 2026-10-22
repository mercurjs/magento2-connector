<?php

declare(strict_types=1);

namespace Mercur\Frontend\Block\Seller;

use Magento\Catalog\Block\Product\AbstractProduct;
use Magento\Catalog\Block\Product\Context;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable as ConfigurableResource;
use Magento\Directory\Model\CountryFactory;
use Magento\Framework\Pricing\Helper\Data as PriceHelper;
use Mercur\Connector\Model\OfferRepository;
use Mercur\Connector\Model\Seller;
use Mercur\Frontend\Controller\Seller\View as ViewController;

/**
 * Seller storefront: who they are and every product they currently sell here.
 */
class View extends AbstractProduct
{
    private const PRODUCT_LIMIT = 60;

    /** @var array<string, float> sku => lowest offer price of this seller */
    private array $prices = [];

    private ?Collection $products = null;

    public function __construct(
        Context $context,
        private readonly OfferRepository $offers,
        private readonly ProductCollectionFactory $productCollection,
        private readonly ConfigurableResource $configurable,
        private readonly Visibility $visibility,
        private readonly CountryFactory $countries,
        private readonly PriceHelper $priceHelper,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getSeller(): ?Seller
    {
        $seller = $this->_coreRegistry->registry(ViewController::REGISTRY_KEY);

        return $seller instanceof Seller ? $seller : null;
    }

    public function getLocation(): string
    {
        $seller = $this->getSeller();
        if (!$seller) {
            return '';
        }
        $country = (string) $seller->getData('country_code');
        $countryName = $country !== '' ? (string) $this->countries->create()->loadByCode(strtoupper($country))->getName() : '';

        return implode(', ', array_filter([(string) $seller->getData('city'), $countryName]));
    }

    public function getSellingSince(): ?string
    {
        $since = $this->getSeller()?->getData('selling_since');

        return $since ? $this->formatDate($since, \IntlDateFormatter::LONG) : null;
    }

    public function getProducts(): Collection
    {
        if ($this->products !== null) {
            return $this->products;
        }

        $websiteId = (int) $this->_storeManager->getStore()->getWebsiteId();
        foreach ($this->offers->getActiveForSeller((string) $this->getSeller()?->getSellerId(), $websiteId) as $offer) {
            $sku = $offer->getProductSku();
            $this->prices[$sku] = min($this->prices[$sku] ?? PHP_FLOAT_MAX, $offer->getPrice());
        }

        $ids = [];
        if ($this->prices !== []) {
            $bySku = $this->productCollection->create()->addAttributeToFilter('sku', ['in' => array_keys($this->prices)]);
            foreach ($bySku as $product) {
                $parents = $this->configurable->getParentIdsByChild($product->getId());
                foreach ($parents ?: [$product->getId()] as $id) {
                    $ids[(int) $id] = true;
                    $this->prices['#' . $id] = min($this->prices['#' . $id] ?? PHP_FLOAT_MAX, $this->prices[$product->getSku()]);
                }
            }
        }

        $this->products = $this->productCollection->create()
            ->addAttributeToSelect(['name', 'small_image', 'url_key', 'thumbnail'])
            ->addIdFilter($ids === [] ? [0] : array_keys($ids))
            ->addAttributeToFilter('status', 1)
            ->setVisibility($this->visibility->getVisibleInCatalogIds())
            ->addStoreFilter()
            ->addUrlRewrite()
            ->setPageSize(self::PRODUCT_LIMIT);

        return $this->products;
    }

    public function getSellerPrice(int $productId): ?string
    {
        $price = $this->prices['#' . $productId] ?? null;

        return $price === null ? null : (string) $this->priceHelper->currency($price, true, false);
    }
}
