<?php

declare(strict_types=1);

namespace Mercur\Settlement\Model;

use Magento\Sales\Api\CreditmemoManagementInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\CreditmemoFactory;
use Magento\Sales\Model\Order\Invoice;

/**
 * Refunds the shopper in Magento for money Mercur already gave back to them on
 * paper: online through the payment method when the invoice was captured
 * online, offline otherwise. The credit memo is flagged so it is not reported
 * back to Mercur.
 */
class CreditmemoIssuer
{
    public function __construct(
        private readonly CreditmemoFactory $creditmemos,
        private readonly CreditmemoManagementInterface $management
    ) {
    }

    /**
     * @param array<int, float> $qtys order item id => quantity; every other line is left out
     */
    public function issue(
        Order $order,
        array $qtys,
        float $adjustment,
        float $baseMarketplaceShipping,
        ?string $refundId,
        string $comment
    ): ?Creditmemo {
        if (!$order->canCreditmemo()) {
            return null;
        }

        $data = [
            'qtys' => $this->allQtys($order, $qtys),
            'shipping_amount' => 0,
            'adjustment_positive' => $adjustment,
            'adjustment_negative' => 0,
        ];
        $invoice = $this->onlineInvoice($order);
        $creditmemo = $invoice
            ? $this->creditmemos->createByInvoice($invoice, $data)
            : $this->creditmemos->createByOrder($order, $data);

        $creditmemo->setData('base_mercur_shipping_amount', $baseMarketplaceShipping);
        $creditmemo->collectTotals();
        if ((float) $creditmemo->getBaseGrandTotal() <= 0) {
            return null;
        }

        $creditmemo->setData('mercur_refund_id', $refundId);
        $creditmemo->setData('mercur_reported', 1);
        $creditmemo->addComment($comment, false, true);

        $this->management->refund($creditmemo, $invoice === null);

        return $creditmemo;
    }

    /**
     * @return array<int, float>
     */
    private function allQtys(Order $order, array $qtys): array
    {
        $result = [];
        foreach ($order->getAllItems() as $item) {
            $result[(int) $item->getId()] = (float) ($qtys[(int) $item->getId()] ?? 0);
        }

        return $result;
    }

    private function onlineInvoice(Order $order): ?Invoice
    {
        if (!$order->getPayment()?->canRefund()) {
            return null;
        }
        foreach ($order->getInvoiceCollection() as $invoice) {
            if ($invoice instanceof Invoice && $invoice->canRefund() && $invoice->getTransactionId()) {
                return $invoice;
            }
        }

        return null;
    }
}
