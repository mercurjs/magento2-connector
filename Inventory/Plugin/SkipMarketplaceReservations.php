<?php

declare(strict_types=1);

namespace Mercur\Inventory\Plugin;

use Magento\InventorySalesApi\Api\Data\ItemToSellInterface;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterface;
use Magento\InventorySalesApi\Api\Data\SalesEventInterface;
use Magento\InventorySalesApi\Api\PlaceReservationsForSalesEventInterface;
use Mercur\Inventory\Model\MarketplaceQty;

/**
 * Placing, failing, canceling or refunding an order must not reserve or
 * release operator stock for the lines sellers fulfill.
 */
class SkipMarketplaceReservations
{
    private const ORDER_EVENTS = [
        SalesEventInterface::EVENT_ORDER_PLACED,
        SalesEventInterface::EVENT_ORDER_PLACE_FAILED,
        SalesEventInterface::EVENT_ORDER_CANCELED,
        SalesEventInterface::EVENT_CREDITMEMO_CREATED,
    ];

    public function __construct(private readonly MarketplaceQty $marketplaceQty)
    {
    }

    /**
     * @param ItemToSellInterface[] $items
     */
    public function aroundExecute(
        PlaceReservationsForSalesEventInterface $subject,
        callable $proceed,
        array $items,
        SalesChannelInterface $salesChannel,
        SalesEventInterface $salesEvent
    ): void {
        if ($salesEvent->getObjectType() !== SalesEventInterface::OBJECT_TYPE_ORDER
            || !in_array($salesEvent->getType(), self::ORDER_EVENTS, true)) {
            $proceed($items, $salesChannel, $salesEvent);
            return;
        }

        // MSI attaches the increment id without declaring it as an extension attribute.
        $extension = $salesEvent->getExtensionAttributes();
        $incrementId = $extension ? ($extension->__toArray()['objectIncrementId'] ?? null) : null;
        $marketplace = $this->marketplaceQty->forOrder($salesEvent->getObjectId(), $incrementId ? (string) $incrementId : null);
        if ($marketplace === []) {
            $proceed($items, $salesChannel, $salesEvent);
            return;
        }

        $remaining = [];
        foreach ($items as $item) {
            $qty = $item->getQuantity();
            $left = abs($qty) - ($marketplace[$item->getSku()] ?? 0.0);
            if ($left <= 0) {
                continue;
            }
            $item->setQuantity($qty < 0 ? -$left : $left);
            $remaining[] = $item;
        }

        if ($remaining !== []) {
            $proceed($remaining, $salesChannel, $salesEvent);
        }
    }
}
