<?php

declare(strict_types=1);

namespace Mercur\Connector\Model\Fulfillment;

/**
 * Turns Mercur fulfillments into the Magento shipments still missing: one per
 * shipped, uncanceled fulfillment, holding only that fulfillment's lines.
 */
final class ShipmentPlanner
{
    /**
     * @param array $remoteOrder Mercur seller order with fulfillments, their items and labels
     * @param array<string, int> $lineToItemId Mercur line item id => Magento order item id
     * @param array<int, float> $qtyToShip Magento order item id => quantity still shippable
     * @param string[] $alreadyShipped Mercur fulfillment ids that already have a Magento shipment
     * @return array<int, array{fulfillment_id: string, items: array<int, float>, tracks: array<int, array{number: string, url: string|null, carrier: string|null}>}>
     */
    public function plan(array $remoteOrder, array $lineToItemId, array $qtyToShip, array $alreadyShipped): array
    {
        $plans = [];
        foreach ($remoteOrder['fulfillments'] ?? [] as $fulfillment) {
            $id = (string) ($fulfillment['id'] ?? '');
            if ($id === '' || empty($fulfillment['shipped_at']) || !empty($fulfillment['canceled_at'])
                || in_array($id, $alreadyShipped, true)) {
                continue;
            }

            $items = [];
            foreach ($fulfillment['items'] ?? [] as $line) {
                $itemId = $lineToItemId[(string) ($line['line_item_id'] ?? '')] ?? null;
                if ($itemId === null) {
                    continue;
                }
                $qty = min((float) ($line['quantity'] ?? 0), $qtyToShip[$itemId] ?? 0.0);
                if ($qty <= 0) {
                    continue;
                }
                $items[$itemId] = ($items[$itemId] ?? 0.0) + $qty;
                $qtyToShip[$itemId] -= $qty;
            }
            if ($items === []) {
                continue;
            }

            $tracks = [];
            foreach ($fulfillment['labels'] ?? [] as $label) {
                if (!empty($label['tracking_number'])) {
                    $tracks[] = [
                        'number' => (string) $label['tracking_number'],
                        'url' => isset($label['tracking_url']) ? (string) $label['tracking_url'] : null,
                        'carrier' => isset($fulfillment['provider_id']) ? (string) $fulfillment['provider_id'] : null,
                    ];
                }
            }

            $plans[] = ['fulfillment_id' => $id, 'items' => $items, 'tracks' => $tracks];
        }

        return $plans;
    }
}
