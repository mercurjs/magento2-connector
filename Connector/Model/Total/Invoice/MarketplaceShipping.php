<?php

declare(strict_types=1);

namespace Mercur\Connector\Model\Total\Invoice;

use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Invoice\Total\AbstractTotal;

/**
 * Bills the marketplace shipping in full on the first invoice, the way Magento bills its own shipping.
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

        $invoice->setData('mercur_shipping_amount', $amount);
        $invoice->setData('base_mercur_shipping_amount', $baseAmount);
        $invoice->setGrandTotal($invoice->getGrandTotal() + $amount);
        $invoice->setBaseGrandTotal($invoice->getBaseGrandTotal() + $baseAmount);

        return $this;
    }
}
