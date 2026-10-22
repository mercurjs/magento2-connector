<?php

declare(strict_types=1);

namespace Mercur\Connector\Controller\Adminhtml\Order;

use Magento\Backend\App\Action;
use Magento\Framework\Phrase;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Mercur\Connector\Model\Fulfillment\FulfillmentSync;
use Mercur\Connector\Model\Order\Synchronizer;

class Resync extends AbstractAction
{
    public const ADMIN_RESOURCE = 'Mercur_Core::orders';

    public function __construct(
        Action\Context $context,
        OrderRepositoryInterface $orders,
        private readonly Synchronizer $synchronizer,
        private readonly FulfillmentSync $fulfillments
    ) {
        parent::__construct($context, $orders);
    }

    protected function run(Order $order): Phrase
    {
        $sellerOrders = array_keys($this->synchronizer->itemsBySellerOrder($order));
        foreach ($sellerOrders as $sellerOrderId) {
            $this->fulfillments->sync((string) $sellerOrderId);
        }

        return __('%1 seller order(s) were resynced from Mercur.', count($sellerOrders));
    }
}
