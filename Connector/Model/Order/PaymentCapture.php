<?php

declare(strict_types=1);

namespace Mercur\Connector\Model\Order;

use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Mercur\Core\Model\Api\AdminApi;

/**
 * Mercur authorizes the external payment when the cart completes; the money
 * is captured in Magento. Once an invoice is paid the capture is confirmed on
 * the order group so seller payouts can run; a canceled order releases it.
 */
class PaymentCapture
{
    public function __construct(
        private readonly AdminApi $admin,
        private readonly OrderRepositoryInterface $orders
    ) {
    }

    public function canCapture(Order $order): bool
    {
        return (string) $order->getData('mercur_order_group_id') !== ''
            && !$order->getData('mercur_captured_at')
            && (float) $order->getTotalPaid() > 0;
    }

    public function capture(Order $order, bool $save = true): bool
    {
        if (!$this->canCapture($order)) {
            return false;
        }

        $this->admin->captureGroupPayment((string) $order->getData('mercur_order_group_id'), (string) $order->getIncrementId());
        $order->setData('mercur_captured_at', gmdate('Y-m-d H:i:s'));
        $order->addCommentToStatusHistory(__('Marketplace payment captured in Mercur.'));
        if ($save) {
            $this->orders->save($order);
        }

        return true;
    }

    public function cancel(Order $order): bool
    {
        $groupId = (string) $order->getData('mercur_order_group_id');
        if ($groupId === '' || $order->getData('mercur_captured_at')) {
            return false;
        }

        $this->admin->cancelGroupPayment($groupId, (string) $order->getIncrementId());
        $order->addCommentToStatusHistory(__('Marketplace payment authorization released in Mercur.'));

        return true;
    }
}
