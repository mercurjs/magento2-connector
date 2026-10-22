<?php

declare(strict_types=1);

namespace Mercur\Settlement\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\ResourceModel\Attribute;
use Mercur\Core\Model\Api\AdminApi;
use Mercur\Core\Model\Config;
use Mercur\Settlement\Model\RefundAllocator;
use Psr\Log\LoggerInterface;

/**
 * Money moves in Magento. Each credit memo that touches marketplace lines is
 * reported to Mercur per seller order so commission lines and payout balances
 * follow the refund. Credit memos the connector issued for a Mercur refund are
 * already known there and are skipped.
 */
class ReportCreditMemo implements ObserverInterface
{
    public function __construct(
        private readonly AdminApi $api,
        private readonly Config $config,
        private readonly RefundAllocator $allocator,
        private readonly Attribute $attributes,
        private readonly Json $json,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(Observer $observer): void
    {
        $creditmemo = $observer->getEvent()->getData('creditmemo');
        if (!$creditmemo instanceof Creditmemo
            || !$this->config->isEnabled((int) $creditmemo->getStoreId())
            || $creditmemo->getData('mercur_reported')) {
            return;
        }

        $lines = [];
        foreach ($creditmemo->getAllItems() as $item) {
            $orderItem = $item->getOrderItem();
            $sellerOrder = (string) $orderItem->getData('mercur_order_id');
            if ($sellerOrder === '' || $orderItem->getParentItem() || (float) $item->getQty() <= 0) {
                continue;
            }
            $lines[] = [
                'seller_order' => $sellerOrder,
                'seller' => (string) $orderItem->getData('mercur_seller_id'),
                'amount' => (float) $item->getBaseRowTotalInclTax() - (float) $item->getBaseDiscountAmount(),
            ];
        }

        $amounts = $this->allocator->allocate(
            $lines,
            (float) $creditmemo->getData('base_mercur_shipping_amount'),
            $this->shippingBySeller((string) $creditmemo->getOrder()->getData('mercur_shipping_methods'))
        );

        $failed = false;
        foreach ($amounts as $sellerOrder => $amount) {
            try {
                $this->api->recordExternalRefund(
                    $sellerOrder,
                    $amount,
                    (string) $creditmemo->getIncrementId(),
                    (string) ($creditmemo->getCustomerNote() ?: '')
                );
            } catch (\Throwable $e) {
                $failed = true;
                $this->logger->error('Mercur refund report failed', ['creditmemo' => $creditmemo->getIncrementId(), 'exception' => $e]);
            }
        }

        if ($amounts !== [] && !$failed) {
            $creditmemo->setData('mercur_reported', 1);
            $this->attributes->saveAttribute($creditmemo, 'mercur_reported');
        }
    }

    /**
     * @return array<string, float>
     */
    private function shippingBySeller(string $raw): array
    {
        $methods = $raw !== '' ? $this->json->unserialize($raw) : [];
        $result = [];
        foreach (is_array($methods) ? $methods : [] as $seller => $method) {
            $result[(string) $seller] = is_array($method) ? (float) ($method['amount'] ?? 0) : 0.0;
        }

        return $result;
    }
}
