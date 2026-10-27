<?php

declare(strict_types=1);

namespace Mercur\Connector\Block\Sales;

use Magento\Framework\DataObject;
use Magento\Framework\View\Element\AbstractBlock;
use Mercur\Connector\Model\Carrier\Marketplace;

/**
 * Adds the "Seller shipping" row to order, invoice and credit memo totals,
 * on the storefront, in emails and in the admin.
 */
class MarketplaceShippingTotal extends AbstractBlock
{
    public function initTotals(): self
    {
        $parent = $this->getParentBlock();
        $source = $parent?->getSource();
        if (!$source) {
            return $this;
        }

        $amount = (float) $source->getData('mercur_shipping_amount');
        if ($amount <= 0) {
            return $this;
        }

        $parent->addTotal(new DataObject([
            'code' => 'mercur_shipping',
            'value' => $amount,
            'base_value' => (float) $source->getData('base_mercur_shipping_amount'),
            'label' => __('Seller shipping'),
        ]), 'shipping');

        $order = $source->getOrder() ?: $source;
        if ($order->getShippingMethod() === Marketplace::CODE . '_' . Marketplace::CODE
            && (float) $source->getData('shipping_amount') <= 0) {
            $parent->removeTotal('shipping');
        }

        return $this;
    }
}
