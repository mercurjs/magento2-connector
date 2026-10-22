<?php

declare(strict_types=1);

namespace Mercur\Connector\Model\Webhook\Handler;

use Magento\Sales\Api\OrderRepositoryInterface;
use Mercur\Connector\Model\Fulfillment\StatusResolver;
use Mercur\Connector\Model\Order\SellerOrderLocator;
use Mercur\Connector\Model\Webhook\HandlerInterface;

/**
 * A seller order was canceled in Mercur. The Magento order keeps its other
 * lines; the canceled ones are flagged. Mercur_Settlement refunds them.
 */
class OrderCanceled implements HandlerInterface
{
    public function __construct(
        private readonly SellerOrderLocator $locator,
        private readonly OrderRepositoryInterface $orderRepository
    ) {
    }

    public function handle(string $event, array $data): void
    {
        $sellerOrderId = (string) ($data['id'] ?? '');
        $order = $this->locator->find($sellerOrderId);
        if ($order === null) {
            return;
        }

        foreach ($order->getAllVisibleItems() as $item) {
            if ($item->getData('mercur_order_id') === $sellerOrderId) {
                $item->setData('mercur_status', StatusResolver::CANCELED);
            }
        }
        $order->addCommentToStatusHistory(__('Seller order %1 was canceled in Mercur.', $sellerOrderId));
        $this->orderRepository->save($order);
    }
}
