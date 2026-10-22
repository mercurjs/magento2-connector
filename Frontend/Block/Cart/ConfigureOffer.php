<?php

declare(strict_types=1);

namespace Mercur\Frontend\Block\Cart;

use Magento\Catalog\Model\Product;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

/**
 * On the cart's "edit item" page the buy request is replayed into the add-to-
 * cart form; this keeps the seller offer in it so the updated line stays with
 * the same seller.
 */
class ConfigureOffer extends Template
{
    public function __construct(
        Context $context,
        private readonly Registry $registry,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getOfferId(): string
    {
        $product = $this->registry->registry('current_product');
        if (!$product instanceof Product) {
            return '';
        }

        return (string) $product->getPreconfiguredValues()->getData('mercur_offer_id');
    }
}
