<?php

declare(strict_types=1);

namespace Mercur\Connector\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;
use Mercur\Connector\Model\Order\SendGate;
use Mercur\Connector\Model\Order\Synchronizer;
use Mercur\Core\Model\Config;
use Psr\Log\LoggerInterface;

/**
 * sales_order_save_commit_after: once the order is committed in one of the
 * configured statuses it is created in Mercur. The event fires on every save,
 * so an order that already has its group is left alone.
 */
class SendMarketplaceOrder implements ObserverInterface
{
    public function __construct(
        private readonly Synchronizer $synchronizer,
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

        $send = SendGate::shouldSend(
            (string) $order->getStatus(),
            $this->config->getAutoCreateStatuses((int) $order->getStoreId()),
            (string) $order->getData('mercur_order_group_id') !== '' || (bool) $order->getData('mercur_sent_at'),
            (string) $order->getData('mercur_cart_id') !== ''
        );
        if (!$send) {
            return;
        }

        try {
            $this->synchronizer->place($order);
        } catch (\Throwable $e) {
            // The shopper has paid; the order must not fail because Mercur was unreachable.
            // The retry task picks orders without a group id up again.
            $this->logger->error('Mercur order placement failed', [
                'order' => $order->getIncrementId(),
                'exception' => $e,
            ]);
            $order->addCommentToStatusHistory(__('Marketplace order could not be created in Mercur: %1', $e->getMessage()));
        }
    }
}
