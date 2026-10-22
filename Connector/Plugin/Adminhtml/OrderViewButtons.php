<?php

declare(strict_types=1);

namespace Mercur\Connector\Plugin\Adminhtml;

use Magento\Framework\AuthorizationInterface;
use Magento\Framework\View\LayoutInterface;
use Magento\Sales\Block\Adminhtml\Order\View;
use Magento\Sales\Model\Order;
use Mercur\Connector\Model\Order\PaymentCapture;
use Mercur\Connector\Model\Order\Synchronizer;
use Mercur\Core\Model\Config;

class OrderViewButtons
{
    public function __construct(
        private readonly Synchronizer $synchronizer,
        private readonly PaymentCapture $paymentCapture,
        private readonly AuthorizationInterface $authorization,
        private readonly Config $config
    ) {
    }

    public function beforeSetLayout(View $subject, LayoutInterface $layout): array
    {
        $order = $subject->getOrder();
        if (!$order instanceof Order
            || !$this->authorization->isAllowed('Mercur_Core::orders')
            || !$this->config->isEnabled((int) $order->getStoreId())
            || (string) $order->getData('mercur_cart_id') === ''
            || $this->synchronizer->marketplaceItems($order) === []) {
            return [$layout];
        }

        $groupId = (string) $order->getData('mercur_order_group_id');
        $orderId = (int) $order->getId();

        if ($groupId === '' && !in_array($order->getState(), [Order::STATE_CANCELED, Order::STATE_CLOSED], true)) {
            $subject->addButton('mercur_send', [
                'label' => __('Send to marketplace'),
                'class' => 'mercur-send',
                'onclick' => $this->confirm(__('Create this order in Mercur now?'), $subject->getUrl('mercur/order/send', ['order_id' => $orderId])),
            ]);
        }
        if ($this->paymentCapture->canCapture($order)) {
            $subject->addButton('mercur_capture', [
                'label' => __('Capture marketplace payment'),
                'class' => 'mercur-capture',
                'onclick' => $this->confirm(__('Confirm the payment capture to Mercur?'), $subject->getUrl('mercur/order/capture', ['order_id' => $orderId])),
            ]);
        }
        if ($groupId !== '') {
            $subject->addButton('mercur_resync', [
                'label' => __('Resync marketplace status'),
                'class' => 'mercur-resync',
                'onclick' => 'setLocation(\'' . $subject->getUrl('mercur/order/resync', ['order_id' => $orderId]) . '\')',
            ]);
        }

        return [$layout];
    }

    private function confirm(\Magento\Framework\Phrase $message, string $url): string
    {
        return 'confirmSetLocation(\'' . addslashes((string) $message) . '\', \'' . $url . '\')';
    }
}
