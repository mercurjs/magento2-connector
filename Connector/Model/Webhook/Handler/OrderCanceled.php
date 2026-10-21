<?php

declare(strict_types=1);

namespace Mercur\Connector\Model\Webhook\Handler;

use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Mercur\Connector\Model\Webhook\HandlerInterface;

/**
 * A seller order was canceled in Mercur. The Magento order keeps its other
 * lines; the canceled lines are flagged and a comment tells the operator to refund.
 */
class OrderCanceled implements HandlerInterface
{
    public function __construct(
        private readonly CollectionFactory $orders,
        private readonly OrderRepositoryInterface $orderRepository
    ) {
    }

    public function handle(string $event, array $data): void
    {
        $sellerOrderId = (string) ($data['id'] ?? '');
        if ($sellerOrderId === '') {
            return;
        }

        $collection = $this->orders->create();
        $collection->getSelect()->join(
            ['item' => $collection->getTable('sales_order_item')],
            'item.order_id = main_table.entity_id',
            []
        )->where('item.mercur_order_id = ?', $sellerOrderId)->group('main_table.entity_id')->limit(1);
        $order = $collection->getFirstItem();
        if (!$order->getId()) {
            return;
        }

        foreach ($order->getAllVisibleItems() as $item) {
            if ($item->getData('mercur_order_id') === $sellerOrderId) {
                $item->setData('mercur_status', 'canceled');
            }
        }
        $order->addCommentToStatusHistory(__('Seller order %1 was canceled in Mercur. Refund the affected lines.', $sellerOrderId));
        $this->orderRepository->save($order);
    }
}
