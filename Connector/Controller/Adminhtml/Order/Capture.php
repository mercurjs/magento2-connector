<?php

declare(strict_types=1);

namespace Mercur\Connector\Controller\Adminhtml\Order;

use Magento\Backend\App\Action;
use Magento\Framework\Phrase;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Mercur\Connector\Model\Order\PaymentCapture;

class Capture extends AbstractAction
{
    public const ADMIN_RESOURCE = 'Mercur_Core::orders';

    public function __construct(
        Action\Context $context,
        OrderRepositoryInterface $orders,
        private readonly PaymentCapture $capture
    ) {
        parent::__construct($context, $orders);
    }

    protected function run(Order $order): Phrase
    {
        return $this->capture->capture($order)
            ? __('The payment capture was confirmed to Mercur.')
            : __('There is nothing to capture: the order is not in Mercur, not paid, or already captured.');
    }
}
