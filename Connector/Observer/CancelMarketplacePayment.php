<?php

declare(strict_types=1);

namespace Mercur\Connector\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;
use Mercur\Connector\Model\Order\PaymentCapture;
use Mercur\Core\Model\Config;
use Psr\Log\LoggerInterface;

/**
 * order_cancel_after: an order canceled before capture releases the
 * authorization held on its Mercur order group.
 */
class CancelMarketplacePayment implements ObserverInterface
{
    public function __construct(
        private readonly PaymentCapture $capture,
        private readonly Config $config,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(Observer $observer): void
    {
        $order = $observer->getEvent()->getData('order');
        if (!$order instanceof Order || !$this->config->isEnabled((int) $order->getStoreId())) {
            return;
        }

        try {
            $this->capture->cancel($order);
        } catch (\Throwable $e) {
            $this->logger->error('Mercur payment cancel failed', ['order' => $order->getIncrementId(), 'exception' => $e]);
            $order->addCommentToStatusHistory(__('Marketplace payment could not be released in Mercur: %1', $e->getMessage()));
        }
    }
}
