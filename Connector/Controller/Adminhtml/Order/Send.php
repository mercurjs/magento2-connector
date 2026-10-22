<?php

declare(strict_types=1);

namespace Mercur\Connector\Controller\Adminhtml\Order;

use Magento\Backend\App\Action;
use Magento\Framework\Phrase;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Mercur\Connector\Model\Order\Synchronizer;

class Send extends AbstractAction
{
    public const ADMIN_RESOURCE = 'Mercur_Core::orders';

    public function __construct(
        Action\Context $context,
        OrderRepositoryInterface $orders,
        private readonly Synchronizer $synchronizer
    ) {
        parent::__construct($context, $orders);
    }

    protected function run(Order $order): Phrase
    {
        if ((string) $order->getData('mercur_order_group_id') !== '') {
            return __('The order is already in Mercur.');
        }
        $this->synchronizer->place($order);

        return (string) $order->getData('mercur_order_group_id') !== ''
            ? __('The order was created in Mercur.')
            : __('The order was not created in Mercur. Check the log.');
    }
}
