<?php

declare(strict_types=1);

namespace Mercur\Connector\Model\Webhook\Handler;

use Magento\Sales\Api\OrderRepositoryInterface;
use Mercur\Connector\Model\Fulfillment\StatusResolver;
use Mercur\Connector\Model\Order\SellerOrderLocator;
use Mercur\Connector\Model\Webhook\HandlerInterface;
use Mercur\Core\Model\Api\AdminApi;
use Psr\Log\LoggerInterface;

/**
 * A seller order was canceled in Mercur. The Magento order keeps its other
 * lines; the canceled ones are flagged, as refused when the seller itself
 * declined the order. Mercur_Settlement refunds them.
 */
class OrderCanceled implements HandlerInterface
{
    public function __construct(
        private readonly SellerOrderLocator $locator,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly AdminApi $api,
        private readonly LoggerInterface $logger
    ) {
    }

    public function handle(string $event, array $data): void
    {
        $sellerOrderId = (string) ($data['id'] ?? '');
        $order = $this->locator->find($sellerOrderId);
        if ($order === null) {
            return;
        }

        $status = StatusResolver::CANCELED;
        try {
            $remote = $this->api->getOrder($sellerOrderId, 'id,canceled_by,seller.id');
            $status = StatusResolver::cancelStatus(
                isset($remote['canceled_by']) ? (string) $remote['canceled_by'] : null,
                isset($remote['seller']['id']) ? (string) $remote['seller']['id'] : null
            );
        } catch (\Throwable $e) {
            $this->logger->warning('Could not read who canceled the Mercur seller order', ['order' => $sellerOrderId, 'exception' => $e]);
        }

        foreach ($order->getAllVisibleItems() as $item) {
            if ($item->getData('mercur_order_id') === $sellerOrderId) {
                $item->setData('mercur_status', $status);
                $item->setData(
                    'mercur_cancel_reason',
                    $status === StatusResolver::REFUSED ? StatusResolver::CANCEL_REASON_SELLER : StatusResolver::CANCEL_REASON_OTHER
                );
            }
        }
        $order->addCommentToStatusHistory($status === StatusResolver::REFUSED
            ? __('Seller order %1 was refused by the seller in Mercur.', $sellerOrderId)
            : __('Seller order %1 was canceled in Mercur.', $sellerOrderId));
        $this->orderRepository->save($order);
    }
}
