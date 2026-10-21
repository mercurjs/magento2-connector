<?php

declare(strict_types=1);

namespace Mercur\Connector\Model\Webhook\Handler;

use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Convert\Order as OrderConverter;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Shipment\TrackFactory;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Mercur\Connector\Model\Webhook\HandlerInterface;
use Mercur\Core\Model\Api\AdminApi;
use Psr\Log\LoggerInterface;

/**
 * A seller shipped in the vendor panel: create the matching Magento shipment for
 * that seller's lines, with the tracking numbers Mercur has.
 */
class OrderShipment implements HandlerInterface
{
    public function __construct(
        private readonly CollectionFactory $orders,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly OrderConverter $converter,
        private readonly TrackFactory $trackFactory,
        private readonly AdminApi $api,
        private readonly LoggerInterface $logger
    ) {
    }

    public function handle(string $event, array $data): void
    {
        $sellerOrderId = (string) ($data['order_id'] ?? $data['id'] ?? '');
        if ($sellerOrderId === '') {
            return;
        }

        $order = $this->findOrder($sellerOrderId);
        if ($order === null) {
            $this->logger->info('Mercur shipment for an unknown seller order', ['seller_order' => $sellerOrderId]);

            return;
        }

        $items = array_filter(
            $order->getAllVisibleItems(),
            static fn ($item) => $item->getData('mercur_order_id') === $sellerOrderId && $item->getQtyToShip() > 0
        );
        if ($items === []) {
            return;
        }

        $shipment = $this->converter->toShipment($order);
        foreach ($items as $item) {
            $shipmentItem = $this->converter->itemToShipmentItem($item)->setQty($item->getQtyToShip());
            $shipment->addItem($shipmentItem);
            $item->setData('mercur_status', 'shipped');
        }

        foreach ($this->trackingNumbers($sellerOrderId, $data) as $tracking) {
            $shipment->addTrack($this->trackFactory->create()->addData([
                'carrier_code' => 'custom',
                'title' => $tracking['carrier'] ?? __('Seller carrier'),
                'number' => $tracking['number'],
            ]));
        }

        $shipment->register();
        $shipment->getOrder()->setIsInProcess(true);
        $shipment->save();
        $this->orderRepository->save($order);
    }

    private function findOrder(string $sellerOrderId): ?Order
    {
        $collection = $this->orders->create();
        $collection->getSelect()->join(
            ['item' => $collection->getTable('sales_order_item')],
            'item.order_id = main_table.entity_id',
            []
        )->where('item.mercur_order_id = ?', $sellerOrderId)->group('main_table.entity_id')->limit(1);

        /** @var Order|false $order */
        $order = $collection->getFirstItem();

        return $order && $order->getId() ? $order : null;
    }

    /**
     * @return array<int, array{number: string, carrier?: string}>
     */
    private function trackingNumbers(string $sellerOrderId, array $data): array
    {
        $labels = $data['labels'] ?? null;
        if (!is_array($labels)) {
            try {
                $remote = $this->api->getOrder($sellerOrderId, 'id,fulfillments.labels.tracking_number,fulfillments.labels.tracking_url,fulfillments.provider_id');
                $labels = [];
                foreach ($remote['fulfillments'] ?? [] as $fulfillment) {
                    foreach ($fulfillment['labels'] ?? [] as $label) {
                        $labels[] = $label + ['carrier' => $fulfillment['provider_id'] ?? null];
                    }
                }
            } catch (\Throwable $e) {
                $this->logger->warning('Could not read Mercur fulfillment labels', ['exception' => $e]);
                $labels = [];
            }
        }

        $result = [];
        foreach ($labels as $label) {
            $number = $label['tracking_number'] ?? null;
            if ($number) {
                $result[] = ['number' => (string) $number, 'carrier' => $label['carrier'] ?? null];
            }
        }

        return $result;
    }
}
