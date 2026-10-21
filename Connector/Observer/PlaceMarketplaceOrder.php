<?php

declare(strict_types=1);

namespace Mercur\Connector\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Mercur\Connector\Model\Order\Synchronizer;
use Mercur\Core\Model\Config;
use Psr\Log\LoggerInterface;

class PlaceMarketplaceOrder implements ObserverInterface
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
        if (!$order || !$this->config->isEnabled((int) $order->getStoreId())) {
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
