<?php

declare(strict_types=1);

namespace Mercur\Settlement\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order\Creditmemo;
use Mercur\Core\Model\Api\AdminApi;
use Mercur\Core\Model\Config;
use Psr\Log\LoggerInterface;

/**
 * Money moves in Magento. Each credit memo that touches marketplace lines is
 * reported to Mercur per seller order so commission lines and payout balances
 * follow the refund.
 */
class ReportCreditMemo implements ObserverInterface
{
    public function __construct(
        private readonly AdminApi $api,
        private readonly Config $config,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(Observer $observer): void
    {
        /** @var Creditmemo $creditmemo */
        $creditmemo = $observer->getEvent()->getData('creditmemo');
        if (!$creditmemo || !$this->config->isEnabled((int) $creditmemo->getStoreId()) || $creditmemo->getData('mercur_reported')) {
            return;
        }

        $amounts = [];
        foreach ($creditmemo->getAllItems() as $item) {
            $orderItem = $item->getOrderItem();
            $sellerOrder = (string) $orderItem->getData('mercur_order_id');
            if ($sellerOrder === '' || (float) $item->getQty() <= 0) {
                continue;
            }
            $amounts[$sellerOrder] = ($amounts[$sellerOrder] ?? 0.0) + (float) $item->getBaseRowTotalInclTax() - (float) $item->getBaseDiscountAmount();
        }

        foreach ($amounts as $sellerOrder => $amount) {
            if ($amount <= 0) {
                continue;
            }
            try {
                $this->api->recordExternalRefund(
                    $sellerOrder,
                    round($amount, 2),
                    (string) $creditmemo->getIncrementId(),
                    (string) ($creditmemo->getCustomerNote() ?: '')
                );
            } catch (\Throwable $e) {
                $this->logger->error('Mercur refund report failed', ['creditmemo' => $creditmemo->getIncrementId(), 'exception' => $e]);
            }
        }

        $creditmemo->setData('mercur_reported', true);
    }
}
