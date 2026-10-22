<?php

declare(strict_types=1);

namespace Mercur\Connector\Controller\Adminhtml\Order;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;

abstract class AbstractAction extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Mercur_Core::orders';

    public function __construct(
        Action\Context $context,
        private readonly OrderRepositoryInterface $orders
    ) {
        parent::__construct($context);
    }

    public function execute(): Redirect
    {
        $orderId = (int) $this->getRequest()->getParam('order_id');
        $redirect = $this->resultRedirectFactory->create();
        try {
            $order = $this->orders->get($orderId);
        } catch (NoSuchEntityException) {
            $this->messageManager->addErrorMessage(__('This order no longer exists.'));

            return $redirect->setPath('sales/order/index');
        }

        if ($order instanceof Order) {
            try {
                $this->messageManager->addSuccessMessage($this->run($order));
            } catch (\Throwable $e) {
                $this->messageManager->addErrorMessage(__('Marketplace action failed: %1', $e->getMessage()));
            }
        }

        return $redirect->setPath('sales/order/view', ['order_id' => $orderId]);
    }

    abstract protected function run(Order $order): \Magento\Framework\Phrase;
}
