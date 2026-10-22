<?php

declare(strict_types=1);

namespace Mercur\Connector\Model\Order;

use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;

/**
 * Finds the Magento order that holds the lines of a Mercur seller order.
 */
class SellerOrderLocator
{
    public function __construct(
        private readonly CollectionFactory $orders,
        private readonly OrderRepositoryInterface $orderRepository
    ) {
    }

    public function find(string $sellerOrderId): ?Order
    {
        if ($sellerOrderId === '') {
            return null;
        }
        $collection = $this->orders->create();
        $collection->getSelect()->join(
            ['item' => $collection->getTable('sales_order_item')],
            'item.order_id = main_table.entity_id',
            []
        )->where('item.mercur_order_id = ?', $sellerOrderId)->group('main_table.entity_id')->limit(1);
        $id = (int) $collection->getFirstItem()->getId();
        if ($id === 0) {
            return null;
        }
        $order = $this->orderRepository->get($id);

        return $order instanceof Order ? $order : null;
    }
}
