<?php

declare(strict_types=1);

namespace Mercur\Connector\Model\Fulfillment;

use Magento\Sales\Api\Data\ShipmentCommentCreationInterfaceFactory;
use Magento\Sales\Api\Data\ShipmentItemCreationInterfaceFactory;
use Magento\Sales\Api\Data\ShipmentTrackCreationInterfaceFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\ShipmentRepositoryInterface;
use Magento\Sales\Api\ShipOrderInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order\Shipment\CollectionFactory as ShipmentCollectionFactory;
use Mercur\Connector\Model\Order\SellerOrderLocator;
use Mercur\Connector\Model\Order\Synchronizer;
use Mercur\Core\Model\Api\AdminApi;
use Psr\Log\LoggerInterface;

/**
 * Brings a Magento order in line with one Mercur seller order: a Magento
 * shipment per shipped Mercur fulfillment (so partial and multi-parcel
 * deliveries show up as they happen) and a marketplace status per line.
 * Safe to run again for the same seller order.
 */
class FulfillmentSync
{
    private const FIELDS = '+fulfillments.items.*,+fulfillments.labels.*';

    public function __construct(
        private readonly AdminApi $api,
        private readonly SellerOrderLocator $locator,
        private readonly Synchronizer $synchronizer,
        private readonly ShipmentPlanner $planner,
        private readonly StatusResolver $statuses,
        private readonly ShipOrderInterface $shipOrder,
        private readonly ShipmentItemCreationInterfaceFactory $itemFactory,
        private readonly ShipmentTrackCreationInterfaceFactory $trackFactory,
        private readonly ShipmentCommentCreationInterfaceFactory $commentFactory,
        private readonly ShipmentRepositoryInterface $shipments,
        private readonly ShipmentCollectionFactory $shipmentCollection,
        private readonly OrderRepositoryInterface $orders,
        private readonly LoggerInterface $logger
    ) {
    }

    public function sync(string $sellerOrderId, ?string $comment = null): void
    {
        $order = $this->locator->find($sellerOrderId);
        if ($order === null) {
            $this->logger->info('Mercur update for an unknown seller order', ['seller_order' => $sellerOrderId]);
            return;
        }

        $remote = $this->api->getOrder($sellerOrderId, self::FIELDS);
        $items = $this->synchronizer->itemsBySellerOrder($order)[$sellerOrderId] ?? [];
        if (array_filter($items, static fn ($item) => !$item->getData('mercur_order_line_item_id')) !== []) {
            $this->synchronizer->linkLineItems($sellerOrderId, $items);
        }

        $lineToItemId = [];
        $qtyToShip = [];
        foreach ($items as $item) {
            $lineToItemId[(string) $item->getData('mercur_order_line_item_id')] = (int) $item->getId();
            $qtyToShip[(int) $item->getId()] = (float) $item->getQtyToShip();
        }

        $remoteLines = [];
        foreach ($remote['items'] ?? [] as $line) {
            $remoteLines[(string) $line['id']] = $line;
        }
        foreach ($items as $item) {
            $line = $remoteLines[(string) $item->getData('mercur_order_line_item_id')] ?? null;
            if ($line !== null && $item->getData('mercur_status') !== StatusResolver::CANCELED) {
                $item->setData('mercur_status', $this->statuses->resolve($line));
            }
        }
        if ($comment !== null) {
            $order->addCommentToStatusHistory($comment);
        }
        $this->orders->save($order);

        $plans = $this->planner->plan($remote, $lineToItemId, $qtyToShip, $this->shippedFulfillments($order));
        foreach ($plans as $plan) {
            $this->createShipment($order, $plan);
        }
    }

    private function createShipment(Order $order, array $plan): void
    {
        $items = [];
        foreach ($plan['items'] as $itemId => $qty) {
            $items[] = $this->itemFactory->create()->setOrderItemId($itemId)->setQty($qty);
        }

        $tracks = [];
        $urls = [];
        foreach ($plan['tracks'] as $track) {
            $tracks[] = $this->trackFactory->create()
                ->setCarrierCode('custom')
                ->setTitle($track['carrier'] ?: (string) __('Seller carrier'))
                ->setTrackNumber($track['number']);
            if ($track['url']) {
                $urls[] = $track['number'] . ': ' . $track['url'];
            }
        }

        $comment = null;
        if ($urls !== []) {
            $comment = $this->commentFactory->create()
                ->setComment((string) __('Track your parcel: %1', implode(', ', $urls)))
                ->setIsVisibleOnFront(1);
        }

        $shipmentId = $this->shipOrder->execute((int) $order->getId(), $items, true, $comment !== null, $comment, $tracks);
        $shipment = $this->shipments->get((int) $shipmentId);
        $shipment->setData('mercur_fulfillment_id', $plan['fulfillment_id']);
        $this->shipments->save($shipment);
    }

    /**
     * @return string[]
     */
    private function shippedFulfillments(Order $order): array
    {
        $collection = $this->shipmentCollection->create()
            ->addFieldToFilter('order_id', (int) $order->getId())
            ->addFieldToFilter('mercur_fulfillment_id', ['notnull' => true]);

        return array_map(static fn ($shipment) => (string) $shipment->getData('mercur_fulfillment_id'), $collection->getItems());
    }
}
