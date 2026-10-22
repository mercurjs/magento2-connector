<?php

declare(strict_types=1);

namespace Mercur\Frontend\Model;

use Magento\Catalog\Helper\Data as CatalogHelper;
use Magento\Catalog\Model\Product;
use Magento\Framework\Pricing\Helper\Data as PriceHelper;
use Magento\Tax\Helper\Data as TaxHelper;

/**
 * Shows an offer amount the way the store shows catalog prices: with or
 * without tax, or both, in the display currency.
 */
class OfferPriceView
{
    public function __construct(
        private readonly TaxHelper $taxHelper,
        private readonly CatalogHelper $catalogHelper,
        private readonly PriceHelper $priceHelper
    ) {
    }

    public function amount(Product $product, float $amount): float
    {
        return (float) $this->catalogHelper->getTaxPrice($product, $amount, !$this->taxHelper->displayPriceExcludingTax());
    }

    public function amountExcludingTax(Product $product, float $amount): float
    {
        return (float) $this->catalogHelper->getTaxPrice($product, $amount, false);
    }

    public function showsBoth(): bool
    {
        return $this->taxHelper->displayBothPrices();
    }

    public function format(float $amount): string
    {
        return (string) $this->priceHelper->currency($amount, true, false);
    }

    public function formatted(Product $product, float $amount): string
    {
        return $this->format($this->amount($product, $amount));
    }
}
