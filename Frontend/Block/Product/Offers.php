<?php

declare(strict_types=1);

namespace Mercur\Frontend\Block\Product;

use Magento\Catalog\Block\Product\Context;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Pricing\Price\FinalPrice;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;
use Mercur\Connector\Model\Delivery\DeliveryEstimate;
use Mercur\Connector\Model\Inventory\ProductSalability;
use Mercur\Connector\Model\Offer;
use Mercur\Connector\Model\SellerUrl;
use Mercur\Core\Model\Config;
use Mercur\Frontend\Model\OfferPriceView;
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
        private readonly OfferPriceView $priceView,
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

    /**
     * Tax-aware, in the display currency, for the product the offer sells.
     */
    public function formatPrice(float $amount, ?Product $product = null): string
    {
        $product ??= $this->getProduct();

        return $product ? $this->priceView->formatted($product, $amount) : $this->priceView->format($amount);
    }

    public function formatPriceExcludingTax(float $amount, ?Product $product = null): ?string
    {
        $product ??= $this->getProduct();
        if (!$product || !$this->priceView->showsBoth()) {
            return null;
        }

        return $this->priceView->format($this->priceView->amountExcludingTax($product, $amount));
    }

    public function isOperatorAvailable(): bool
    {
        $product = $this->getProduct();
        if (!$product || $product->getTypeId() === Configurable::TYPE_CODE) {
            return false;
        }
        $salable = (bool) $product->isSalable();

        return $product->hasData(ProductSalability::OWN_SALABLE) ? (bool) $product->getData(ProductSalability::OWN_SALABLE) : $salable;
    }

    public function getOperatorPrice(): ?float
    {
        $product = $this->getProduct();
        if (!$product) {
            return null;
        }
        $amount = (float) $product->getPriceInfo()->getPrice(FinalPrice::PRICE_CODE)->getAmount()->getValue();

        return $amount > 0 ? $amount : null;
    }

    /**
     * Per-child buy box facts the variant scripts read to filter rows and
     * route the main form to a seller offer.
     *
     * @return string JSON keyed by child product id
     */
    public function getChildrenJson(): string
    {
        $children = [];
        foreach ($this->getRows() as $row) {
            $child = $row['child'];
            if ($child === null || isset($children[(string) $child->getId()])) {
                continue;
            }
            $summary = $this->getSummary((string) $child->getSku());
            $children[(string) $child->getId()] = [
                'operator_available' => (int) ($summary['operator_available'] ?? 0),
                'best_offer_id' => $summary['best_offer_id'] ?? null,
            ];
        }

        return (string) json_encode($children);
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
    public function getTierLabels(Offer $offer, ?Product $product = null): array
    {
        $labels = [];
        foreach ($offer->getPriceRanges() as $range) {
            $amount = (float) $range['amount'];
            if ($amount >= $offer->getPrice() || empty($range['min_quantity'])) {
                continue;
            }
            $labels[] = (string) __('Buy %1+ for %2 each', (int) $range['min_quantity'], $this->formatPrice($amount, $product));
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
