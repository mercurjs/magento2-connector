<?php

declare(strict_types=1);

namespace Mercur\Connector\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Mercur\Connector\Model\Order\PaymentCapture;
use Mercur\Core\Model\Config;
use Psr\Log\LoggerInterface;

/**
 * sales_order_invoice_pay: the money is in; the capture is confirmed on the
 * Mercur order group. The order is saved by the invoice transaction.
 */
class CaptureMarketplacePayment implements ObserverInterface
{
    public function __construct(
        private readonly PaymentCapture $capture,
        private readonly Config $config,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(Observer $observer): void
    {
        $invoice = $observer->getEvent()->getData('invoice');
        $order = $invoice instanceof Invoice ? $invoice->getOrder() : null;
        if (!$order instanceof Order || !$this->config->isEnabled((int) $order->getStoreId())) {
            return;
        }

        try {
            $this->capture->capture($order, false);
        } catch (\Throwable $e) {
            $this->logger->error('Mercur payment capture failed', ['order' => $order->getIncrementId(), 'exception' => $e]);
            $order->addCommentToStatusHistory(__('Marketplace payment capture failed: %1. It is retried automatically.', $e->getMessage()));
        }
    }
}
