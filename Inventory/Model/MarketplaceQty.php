<?php

declare(strict_types=1);

namespace Mercur\Inventory\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Sales\Api\Data\OrderItemInterface;

/**
 * How much of each SKU in an order is sold by sellers rather than taken from
 * the operator's sources. Orders still being placed are registered by
 * increment id because they have no entity id yet.
 */
class MarketplaceQty
{
    /** @var array<string, array<string, float>> increment id => sku => qty */
    private array $pending = [];

    private ?string $current = null;

    public function __construct(private readonly ResourceConnection $resource)
    {
    }

    /**
     * @param OrderItemInterface[] $items
     */
    public function register(string $incrementId, array $items): void
    {
        $this->pending[$incrementId] = $this->fromItems($items);
        $this->current = $incrementId;
    }

    /**
     * @return array<string, float>
     */
    public function current(): array
    {
        return $this->current === null ? [] : ($this->pending[$this->current] ?? []);
    }

    /**
     * @return array<string, float>
     */
    public function forOrder(string $orderId, ?string $incrementId): array
    {
        if ($incrementId !== null && isset($this->pending[$incrementId])) {
            return $this->pending[$incrementId];
        }
        if ($orderId === '') {
            return [];
        }

        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName('sales_order_item');
        $select = $connection->select()
            ->from(['item' => $table], ['sku' => 'item.sku', 'qty' => 'SUM(item.qty_ordered)'])
            ->joinLeft(['parent' => $table], 'parent.item_id = item.parent_item_id', [])
            ->where('item.order_id = ?', (int) $orderId)
            ->where('COALESCE(parent.mercur_offer_id, item.mercur_offer_id) IS NOT NULL')
            ->where('NOT EXISTS (SELECT 1 FROM ' . $table . ' child WHERE child.parent_item_id = item.item_id)')
            ->group('item.sku');

        return array_map('floatval', $connection->fetchPairs($select));
    }

    /**
     * Leaf lines only: a configurable parent carries its child's SKU too.
     *
     * @param OrderItemInterface[] $items
     * @return array<string, float>
     */
    public function fromItems(array $items): array
    {
        $parents = [];
        foreach ($items as $item) {
            if ($item->getParentItemId()) {
                $parents[(int) $item->getParentItemId()] = true;
            }
            if ($item->getParentItem()) {
                $parents[spl_object_id($item->getParentItem())] = true;
            }
        }

        $result = [];
        foreach ($items as $item) {
            if (isset($parents[spl_object_id($item)]) || ($item->getItemId() && isset($parents[(int) $item->getItemId()]))) {
                continue;
            }
            $owner = $item->getParentItem() ?: $item;
            if (!$owner->getData('mercur_offer_id')) {
                continue;
            }
            $result[(string) $item->getSku()] = ($result[(string) $item->getSku()] ?? 0.0) + (float) $item->getQtyOrdered();
        }

        return $result;
    }
}
