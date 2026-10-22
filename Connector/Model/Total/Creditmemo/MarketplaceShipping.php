<?php

declare(strict_types=1);

namespace Mercur\Connector\Model\Total\Creditmemo;

use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Creditmemo\Total\AbstractTotal;

/**
 * Refunds marketplace shipping. A credit memo the connector builds for one
 * seller sets the amount itself; otherwise the remainder is refunded once the
 * last seller line is refunded.
 */
class MarketplaceShipping extends AbstractTotal
{
    public function collect(Creditmemo $creditmemo): self
    {
        $order = $creditmemo->getOrder();
        $remaining = (float) $order->getData('mercur_shipping_invoiced') - (float) $order->getData('mercur_shipping_refunded');
        $baseRemaining = (float) $order->getData('base_mercur_shipping_invoiced') - (float) $order->getData('base_mercur_shipping_refunded');

        if ($creditmemo->hasData('base_mercur_shipping_amount')) {
            $baseAmount = min((float) $creditmemo->getData('base_mercur_shipping_amount'), max(0.0, $baseRemaining));
            $amount = $baseRemaining > 0 ? $remaining * $baseAmount / $baseRemaining : 0.0;
        } elseif ($baseRemaining > 0 && $this->refundsLastMarketplaceLine($creditmemo)) {
            $baseAmount = $baseRemaining;
            $amount = $remaining;
        } else {
            return $this;
        }

        $creditmemo->setData('mercur_shipping_amount', $amount);
        $creditmemo->setData('base_mercur_shipping_amount', $baseAmount);
        $creditmemo->setGrandTotal($creditmemo->getGrandTotal() + $amount);
        $creditmemo->setBaseGrandTotal($creditmemo->getBaseGrandTotal() + $baseAmount);

        return $this;
    }

    private function refundsLastMarketplaceLine(Creditmemo $creditmemo): bool
    {
        $refunding = [];
        foreach ($creditmemo->getAllItems() as $item) {
            $refunding[(int) $item->getOrderItemId()] = (float) $item->getQty();
        }

        $touched = false;
        foreach ($creditmemo->getOrder()->getAllVisibleItems() as $orderItem) {
            if (!$orderItem->getData('mercur_offer_id')) {
                continue;
            }
            $qty = $refunding[(int) $orderItem->getId()] ?? 0.0;
            $touched = $touched || $qty > 0;
            if ((float) $orderItem->getQtyRefunded() + $qty < (float) $orderItem->getQtyInvoiced()) {
                return false;
            }
        }

        return $touched;
    }
}
