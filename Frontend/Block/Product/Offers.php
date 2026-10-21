<?php

declare(strict_types=1);

namespace Mercur\Frontend\Block\Product;

use Magento\Catalog\Block\Product\Context;
use Magento\Catalog\Model\Product;
use Magento\Framework\Pricing\Helper\Data as PriceHelper;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;
use Mercur\Connector\Model\Offer;
use Mercur\Core\Model\Config;
use Mercur\OfferIndexer\Model\BuyBox;

/**
 * Product page block: the best way to buy, and every seller offer with its own add-to-cart.
 */
class Offers extends Template
{
    public function __construct(
        Context $context,
        private readonly Registry $registry,
        private readonly BuyBox $buyBox,
        private readonly Config $config,
        private readonly PriceHelper $priceHelper,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function isEnabled(): bool
    {
        return $this->config->isEnabled((int) $this->_storeManager->getStore()->getId());
    }

    public function getProduct(): ?Product
    {
        $product = $this->registry->registry('current_product');

        return $product instanceof Product ? $product : null;
    }

    /**
     * @return Offer[]
     */
    public function getOffers(): array
    {
        $product = $this->getProduct();

        return $product ? $this->buyBox->offers((string) $product->getSku()) : [];
    }

    public function getSummary(): ?array
    {
        $product = $this->getProduct();
        if (!$product) {
            return null;
        }

        return $this->buyBox->summary((string) $product->getSku(), (int) $this->_storeManager->getStore()->getWebsiteId());
    }

    public function isBest(Offer $offer): bool
    {
        return ($this->getSummary()['best_offer_id'] ?? null) === $offer->getOfferId();
    }

    public function isOperatorBest(): bool
    {
        $summary = $this->getSummary();

        return $summary !== null && $summary['best_offer_id'] === null && (bool) $summary['operator_available'];
    }

    public function formatPrice(float $amount): string
    {
        return (string) $this->priceHelper->currency($amount, true, false);
    }

    public function getOperatorLabel(): string
    {
        return $this->config->getOperatorLabel((int) $this->_storeManager->getStore()->getId()) ?: (string) __('Sold by us');
    }

    public function getAddToCartUrl(): string
    {
        return $this->getUrl('checkout/cart/add', ['_secure' => $this->getRequest()->isSecure()]);
    }
}
