<?php

declare(strict_types=1);

namespace Mercur\Connector\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

/**
 * sales_order_invoice_register / sales_order_creditmemo_refund: keeps the
 * order's invoiced and refunded marketplace shipping in step.
 */
class TrackMarketplaceShippingInvoiced implements ObserverInterface
{
    public function execute(Observer $observer): void
    {
        $event = $observer->getEvent();
        $document = $event->getData('invoice') ?? $event->getData('creditmemo');
        $order = $event->getData('order') ?? $document?->getOrder();
        if (!$document || !$order || !(float) $document->getData('base_mercur_shipping_amount')) {
            return;
        }

        $field = $event->getData('invoice') ? 'invoiced' : 'refunded';
        $order->setData(
            'mercur_shipping_' . $field,
            (float) $order->getData('mercur_shipping_' . $field) + (float) $document->getData('mercur_shipping_amount')
        );
        $order->setData(
            'base_mercur_shipping_' . $field,
            (float) $order->getData('base_mercur_shipping_' . $field) + (float) $document->getData('base_mercur_shipping_amount')
        );
    }
}
