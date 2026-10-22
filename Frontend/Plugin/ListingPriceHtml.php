<?php

declare(strict_types=1);

namespace Mercur\Frontend\Plugin;

use Magento\Catalog\Block\Product\AbstractProduct;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Pricing\Price\FinalPrice;
use Magento\Framework\Pricing\Render;
use Magento\Store\Model\StoreManagerInterface;
use Mercur\Connector\Model\Inventory\ProductSalability;
use Mercur\Core\Model\Config;
use Mercur\Frontend\Block\Product\ListingPrice;
use Mercur\Frontend\Model\ListingPriceChooser;
use Mercur\Frontend\Model\ListingSummary;

/**
 * Category and search listings show the best way to buy, so a product only
 * sellers have in stock, or that a seller sells cheaper, does not advertise
 * the operator's price.
 */
class ListingPriceHtml
{
    public function __construct(
        private readonly ListingSummary $summaries,
        private readonly ListingPriceChooser $chooser,
        private readonly Config $config,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * @param string $result
     * @param string $priceType
     * @param string|null $renderZone
     * @return string
     */
    public function afterGetProductPriceHtml(
        AbstractProduct $subject,
        $result,
        Product $product,
        $priceType,
        $renderZone = Render::ZONE_ITEM_LIST,
        array $arguments = []
    ) {
        $zone = $arguments['zone'] ?? $renderZone;
        if ($zone !== Render::ZONE_ITEM_LIST || $priceType !== FinalPrice::PRICE_CODE || !$product->getSku()) {
            return $result;
        }
        $store = $this->storeManager->getStore();
        if (!$this->config->isEnabled((int) $store->getId())) {
            return $result;
        }
        $websiteId = (int) $store->getWebsiteId();
        $summary = $this->summaries->get((string) $product->getSku(), $websiteId);
        if ($summary === null) {
            return $result;
        }

        $ownSalable = (bool) $product->isSalable();
        if ($product->hasData(ProductSalability::OWN_SALABLE)) {
            $ownSalable = (bool) $product->getData(ProductSalability::OWN_SALABLE);
        }
        $ownFinal = (float) $product->getPriceInfo()->getPrice(FinalPrice::PRICE_CODE)->getAmount()->getValue();
        $choice = $this->chooser->choose(
            $ownFinal > 0 ? $ownFinal : null,
            $ownSalable,
            $summary,
            $this->config->getBestOfferRule($websiteId)
        );
        if ($choice === null) {
            return $result;
        }

        /** @var ListingPrice $block */
        $block = $subject->getLayout()->createBlock(ListingPrice::class);
        $block->setData([
            'product' => $product,
            'amount' => $choice['price'],
            'from' => $choice['from'],
            'id_suffix' => $arguments['price_id_suffix'] ?? '',
        ]);

        return $block->toHtml();
    }
}
