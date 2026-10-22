<?php

declare(strict_types=1);

namespace Mercur\Settlement\Model\Webhook;

use Magento\Framework\Serialize\Serializer\Json;
use Mercur\Connector\Model\Order\SellerOrderLocator;
use Mercur\Connector\Model\Webhook\HandlerInterface;
use Mercur\Settlement\Model\CreditmemoIssuer;
use Psr\Log\LoggerInterface;

/**
 * order.canceled: Mercur refunded the canceled seller order inside its own
 * books without telling anyone, so Magento refunds the shopper for that
 * seller's invoiced lines and its shipping.
 */
class RefundCanceledSellerOrder implements HandlerInterface
{
    public function __construct(
        private readonly SellerOrderLocator $locator,
        private readonly CreditmemoIssuer $issuer,
        private readonly Json $json,
        private readonly LoggerInterface $logger
    ) {
    }

    public function handle(string $event, array $data): void
    {
        $sellerOrderId = (string) ($data['id'] ?? $data['order_id'] ?? '');
        $order = $this->locator->find($sellerOrderId);
        if ($order === null) {
            return;
        }

        $qtys = [];
        $sellerId = null;
        foreach ($order->getAllVisibleItems() as $item) {
            if ($item->getData('mercur_order_id') !== $sellerOrderId) {
                continue;
            }
            $sellerId = (string) $item->getData('mercur_seller_id');
            $refundable = (float) $item->getQtyInvoiced() - (float) $item->getQtyRefunded();
            if ($refundable > 0) {
                $qtys[(int) $item->getId()] = $refundable;
                foreach ($item->getChildrenItems() as $child) {
                    $qtys[(int) $child->getId()] = $refundable;
                }
            }
        }
        if ($qtys === []) {
            return;
        }

        try {
            $this->issuer->issue(
                $order,
                $qtys,
                0.0,
                $this->sellerShipping((string) $order->getData('mercur_shipping_methods'), (string) $sellerId),
                null,
                (string) __('Seller order %1 was canceled in Mercur.', $sellerOrderId)
            );
        } catch (\Throwable $e) {
            $this->logger->error('Refund for a canceled seller order failed', ['seller_order' => $sellerOrderId, 'exception' => $e]);
        }
    }

    private function sellerShipping(string $raw, string $sellerId): float
    {
        $methods = $raw !== '' ? $this->json->unserialize($raw) : [];

        return is_array($methods) && is_array($methods[$sellerId] ?? null) ? (float) ($methods[$sellerId]['amount'] ?? 0) : 0.0;
    }
}
