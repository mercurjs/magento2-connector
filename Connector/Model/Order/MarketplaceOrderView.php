<?php

declare(strict_types=1);

namespace Mercur\Connector\Model\Order;

use Magento\Framework\Serialize\Serializer\Json;
use Magento\Sales\Model\Order;
use Mercur\Connector\Model\Fulfillment\StatusResolver;
use Mercur\Connector\Model\ResourceModel\Seller\CollectionFactory as SellerCollectionFactory;
use Mercur\Connector\Model\SellerUrl;

/**
 * One row per Mercur seller order inside a Magento order, for the customer
 * account, the admin order view and GraphQL.
 */
class MarketplaceOrderView
{
    public function __construct(
        private readonly Synchronizer $synchronizer,
        private readonly SellerCollectionFactory $sellers,
        private readonly SellerUrl $sellerUrl,
        private readonly Json $json
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function build(Order $order): array
    {
        $groups = $this->synchronizer->itemsBySellerOrder($order);
        if ($groups === []) {
            return [];
        }

        $sellerIds = [];
        foreach ($groups as $items) {
            $sellerIds[] = (string) $items[0]->getData('mercur_seller_id');
        }
        $sellers = [];
        foreach ($this->sellers->create()->addFieldToFilter('seller_id', ['in' => $sellerIds]) as $seller) {
            $sellers[$seller->getSellerId()] = $seller;
        }

        $shipping = $order->getData('mercur_shipping_methods');
        $shipping = $shipping ? $this->json->unserialize((string) $shipping) : [];
        $tracks = $this->tracksByItem($order);

        $rows = [];
        foreach ($groups as $sellerOrderId => $items) {
            $sellerId = (string) $items[0]->getData('mercur_seller_id');
            $seller = $sellers[$sellerId] ?? null;
            $statuses = array_map(static fn ($item) => (string) ($item->getData('mercur_status') ?: StatusResolver::NOT_FULFILLED), $items);
            $rowTracks = [];
            foreach ($items as $item) {
                foreach ($tracks[(int) $item->getId()] ?? [] as $number => $track) {
                    $rowTracks[$number] = $track;
                }
            }
            $method = is_array($shipping[$sellerId] ?? null) ? $shipping[$sellerId] : [];

            $rows[] = [
                'seller_order_id' => $sellerOrderId,
                'seller_id' => $sellerId,
                'seller_name' => $seller ? $seller->getName() : $sellerId,
                'seller_url' => $seller ? $this->sellerUrl->get((string) $seller->getData('handle')) : '',
                'status' => $this->overallStatus($statuses),
                'shipping_method' => (string) ($method['name'] ?? ''),
                'shipping_amount' => (float) ($method['amount'] ?? 0),
                'items' => array_map(static fn ($item) => [
                    'item_id' => (int) $item->getId(),
                    'name' => (string) $item->getName(),
                    'sku' => (string) $item->getSku(),
                    'qty' => (float) $item->getQtyOrdered(),
                    'status' => (string) ($item->getData('mercur_status') ?: StatusResolver::NOT_FULFILLED),
                ], $items),
                'tracks' => array_values($rowTracks),
            ];
        }

        return $rows;
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            StatusResolver::FULFILLED => (string) __('Preparing'),
            StatusResolver::PARTIALLY_SHIPPED => (string) __('Partially shipped'),
            StatusResolver::SHIPPED => (string) __('Shipped'),
            StatusResolver::DELIVERED => (string) __('Delivered'),
            StatusResolver::RETURN_REQUESTED => (string) __('Return requested'),
            StatusResolver::RETURNED => (string) __('Returned'),
            StatusResolver::CANCELED => (string) __('Canceled'),
            default => (string) __('Processing'),
        };
    }

    /**
     * The least advanced line decides, so a seller order reads "Partially
     * shipped" until every line left.
     *
     * @param string[] $statuses
     */
    private function overallStatus(array $statuses): string
    {
        $statuses = array_values(array_unique($statuses));
        if (count($statuses) === 1) {
            return $statuses[0];
        }
        $shippedOrLater = [StatusResolver::SHIPPED, StatusResolver::DELIVERED, StatusResolver::PARTIALLY_SHIPPED];
        if (array_intersect($statuses, $shippedOrLater) !== []) {
            return StatusResolver::PARTIALLY_SHIPPED;
        }

        return $statuses[0];
    }

    /**
     * @return array<int, array<string, array{number: string, title: string}>> order item id => tracks by number
     */
    private function tracksByItem(Order $order): array
    {
        $result = [];
        foreach ($order->getShipmentsCollection() ?: [] as $shipment) {
            $numbers = [];
            foreach ($shipment->getAllTracks() as $track) {
                $numbers[(string) $track->getTrackNumber()] = [
                    'number' => (string) $track->getTrackNumber(),
                    'title' => (string) $track->getTitle(),
                ];
            }
            foreach ($shipment->getAllItems() as $shipmentItem) {
                $orderItemId = (int) $shipmentItem->getOrderItemId();
                $result[$orderItemId] = array_merge($result[$orderItemId] ?? [], $numbers);
            }
        }

        return $result;
    }
}
