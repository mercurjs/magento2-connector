<?php

declare(strict_types=1);

namespace Mercur\Connector\Model\Total\Invoice;

use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Invoice\Total\AbstractTotal;
use Mercur\Connector\Model\Tax\SellerShippingTax;

/**
 * Bills the marketplace shipping in full on the first invoice, the way Magento
 * bills its own shipping. Only the net amount goes into the grand total: the
 * tax is part of the order's tax_amount, which Magento's invoice Tax total
 * (sort 200) assigns to tax_amount and adds to the grand total itself.
 */
class MarketplaceShipping extends AbstractTotal
{
    public function collect(Invoice $invoice): self
    {
        $order = $invoice->getOrder();
        $amount = (float) $order->getData('mercur_shipping_amount') - (float) $order->getData('mercur_shipping_invoiced');
        $baseAmount = (float) $order->getData('base_mercur_shipping_amount') - (float) $order->getData('base_mercur_shipping_invoiced');
        if ($baseAmount <= 0) {
            return $this;
        }

        $tax = SellerShippingTax::prorate($amount, (float) $order->getData('mercur_shipping_amount'), (float) $order->getData('mercur_shipping_tax_amount'));
        $baseTax = SellerShippingTax::prorate($baseAmount, (float) $order->getData('base_mercur_shipping_amount'), (float) $order->getData('base_mercur_shipping_tax_amount'));

        $invoice->setData('mercur_shipping_amount', $amount);
        $invoice->setData('base_mercur_shipping_amount', $baseAmount);
        $invoice->setData('mercur_shipping_tax_amount', $tax['tax']);
        $invoice->setData('base_mercur_shipping_tax_amount', $baseTax['tax']);
        $invoice->setData('mercur_shipping_incl_tax', $tax['incl']);
        $invoice->setData('base_mercur_shipping_incl_tax', $baseTax['incl']);
        $invoice->setGrandTotal($invoice->getGrandTotal() + $amount);
        $invoice->setBaseGrandTotal($invoice->getBaseGrandTotal() + $baseAmount);

        return $this;
    }
}
