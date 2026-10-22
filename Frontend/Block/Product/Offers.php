<?php

declare(strict_types=1);

namespace Mercur\Frontend\Block\Product;

use Magento\Catalog\Block\Product\Context;
use Magento\Catalog\Model\Product;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\Pricing\Helper\Data as PriceHelper;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;
use Mercur\Connector\Model\Delivery\DeliveryEstimate;
use Mercur\Connector\Model\Offer;
use Mercur\Connector\Model\SellerUrl;
use Mercur\Core\Model\Config;
use Mercur\OfferIndexer\Model\BuyBox;

/**
 * Product page block: the best way to buy, and every seller offer with its own
 * add-to-cart. On a configurable product each offer belongs to one variant.
 */
class Offers extends Template
{
    /** @var array<int, array{offer: Offer, child: Product|null, label: string|null, super_attribute: array<int, string>}>|null */
    private ?array $rows = null;

    public function __construct(
        Context $context,
        private readonly Registry $registry,
        private readonly BuyBox $buyBox,
        private readonly Config $config,
        private readonly PriceHelper $priceHelper,
        private readonly DeliveryEstimate $delivery,
        private readonly SellerUrl $sellerUrl,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function isEnabled(): bool
    {
        return $this->config->isEnabled($this->storeId());
    }

    public function getProduct(): ?Product
    {
        $product = $this->registry->registry('current_product');

        return $product instanceof Product ? $product : null;
    }

    /**
     * @return array<int, array{offer: Offer, child: Product|null, label: string|null, super_attribute: array<int, string>}>
     */
    public function getRows(): array
    {
        if ($this->rows !== null) {
            return $this->rows;
        }
        $this->rows = [];
        $product = $this->getProduct();
        if (!$product) {
            return $this->rows;
        }

        if ($product->getTypeId() !== Configurable::TYPE_CODE) {
            foreach ($this->buyBox->offers((string) $product->getSku(), $this->websiteId()) as $offer) {
                $this->rows[] = ['offer' => $offer, 'child' => null, 'label' => null, 'super_attribute' => []];
            }

            return $this->rows;
        }

        /** @var Configurable $type */
        $type = $product->getTypeInstance();
        $attributes = $type->getConfigurableAttributesAsArray($product);
        foreach ($type->getUsedProducts($product) as $child) {
            $super = [];
            $labels = [];
            foreach ($attributes as $attribute) {
                $code = (string) $attribute['attribute_code'];
                $super[(int) $attribute['attribute_id']] = (string) $child->getData($code);
                $labels[] = (string) $child->getAttributeText($code);
            }
            foreach ($this->buyBox->offers((string) $child->getSku(), $this->websiteId()) as $offer) {
                $this->rows[] = [
                    'offer' => $offer,
                    'child' => $child,
                    'label' => implode(' / ', array_filter($labels)),
                    'super_attribute' => $super,
                ];
            }
        }
        usort($this->rows, static fn ($a, $b) => $a['offer']->getPrice() <=> $b['offer']->getPrice());

        return $this->rows;
    }

    public function getSummary(?string $sku = null): ?array
    {
        $sku ??= (string) $this->getProduct()?->getSku();

        return $sku === '' ? null : $this->buyBox->summary($sku, $this->websiteId());
    }

    public function isBest(Offer $offer): bool
    {
        return ($this->getSummary($offer->getProductSku())['best_offer_id'] ?? null) === $offer->getOfferId();
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
        return $this->config->getOperatorLabel($this->storeId()) ?: (string) __('Sold by us');
    }

    public function getSellerUrl(Offer $offer): string
    {
        return $this->sellerUrl->get($offer->getSellerHandle());
    }

    public function getDeliveryLabel(Offer $offer): ?string
    {
        return $this->delivery->format($this->delivery->estimate($offer->getLeadtimeToShip(), $this->storeId()), $this->storeId());
    }

    /**
     * @return string[] "Buy 10+ for $20.00" lines, cheapest last
     */
    public function getTierLabels(Offer $offer): array
    {
        $labels = [];
        foreach ($offer->getPriceRanges() as $range) {
            $amount = (float) $range['amount'];
            if ($amount >= $offer->getPrice() || empty($range['min_quantity'])) {
                continue;
            }
            $labels[] = (string) __('Buy %1+ for %2 each', (int) $range['min_quantity'], $this->formatPrice($amount));
        }

        return $labels;
    }

    public function getAddToCartUrl(): string
    {
        return $this->getUrl('checkout/cart/add', ['_secure' => $this->getRequest()->isSecure()]);
    }

    private function storeId(): int
    {
        return (int) $this->_storeManager->getStore()->getId();
    }

    private function websiteId(): int
    {
        return (int) $this->_storeManager->getStore()->getWebsiteId();
    }
}
