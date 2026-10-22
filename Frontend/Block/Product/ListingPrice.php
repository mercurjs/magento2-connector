<?php

declare(strict_types=1);

namespace Mercur\Frontend\Block\Product;

use Magento\Catalog\Model\Product;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Mercur\Frontend\Model\OfferPriceView;

class ListingPrice extends Template
{
    protected $_template = 'Mercur_Frontend::product/listing-price.phtml';

    public function __construct(
        Context $context,
        private readonly OfferPriceView $priceView,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getProduct(): Product
    {
        return $this->getData('product');
    }

    public function getAmount(): float
    {
        return $this->priceView->amount($this->getProduct(), (float) $this->getData('amount'));
    }

    public function getAmountExcludingTax(): float
    {
        return $this->priceView->amountExcludingTax($this->getProduct(), (float) $this->getData('amount'));
    }

    public function isFrom(): bool
    {
        return (bool) $this->getData('from');
    }

    public function showsBoth(): bool
    {
        return $this->priceView->showsBoth();
    }

    public function format(float $amount): string
    {
        return $this->priceView->format($amount);
    }
}
