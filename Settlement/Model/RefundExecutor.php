<?php

declare(strict_types=1);

namespace Mercur\Settlement\Model;

use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\ResourceModel\Order\Creditmemo\CollectionFactory as CreditmemoCollectionFactory;
use Mercur\Connector\Model\Order\SellerOrderLocator;
use Psr\Log\LoggerInterface;

/**
 * Executes a refund a seller or the operator started in Mercur. Mercur only
 * knows the amount, so the credit memo is an adjustment refund. Each Mercur
 * refund produces at most one credit memo.
 */
class RefundExecutor
{
    public function __construct(
        private readonly SellerOrderLocator $locator,
        private readonly CreditmemoIssuer $issuer,
        private readonly CreditmemoCollectionFactory $creditmemos,
        private readonly OrderRepositoryInterface $orders,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(string $sellerOrderId, string $refundId, float $amount, ?string $note = null): bool
    {
        if ($refundId === '' || $amount <= 0 || $this->alreadyRefunded($refundId)) {
            return false;
        }

        $order = $this->locator->find($sellerOrderId);
        if ($order === null) {
            $this->logger->info('Mercur refund for an unknown seller order', ['seller_order' => $sellerOrderId]);
            return false;
        }

        $comment = (string) __('Refund %1 from seller order %2 in Mercur.', $refundId, $sellerOrderId)
            . ($note ? ' ' . $note : '');
        $creditmemo = $this->issuer->issue($order, [], $amount, 0.0, $refundId, $comment);
        if ($creditmemo === null) {
            $order->addCommentToStatusHistory((string) __(
                'Mercur refund %1 of %2 could not be refunded automatically. Refund it manually.',
                $refundId,
                $amount
            ));
            $this->orders->save($order);
            return false;
        }

        return true;
    }

    private function alreadyRefunded(string $refundId): bool
    {
        return $this->creditmemos->create()->addFieldToFilter('mercur_refund_id', $refundId)->getSize() > 0;
    }
}
