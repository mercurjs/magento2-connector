<?php

declare(strict_types=1);

namespace Mercur\Inventory\Plugin;

use Magento\InventoryShipping\Model\GetItemsToDeductFromShipment;
use Magento\InventorySourceDeductionApi\Model\ItemToDeductInterface;
use Magento\InventorySourceDeductionApi\Model\ItemToDeductInterfaceFactory;
use Magento\Sales\Model\Order\Shipment;

/**
 * A shipment Mercur created for a seller parcel never takes stock from the
 * operator's sources.
 */
class SkipMarketplaceSourceDeduction
{
    public function __construct(
        private readonly ItemToDeductInterfaceFactory $itemFactory
    ) {
    }

    /**
     * @param ItemToDeductInterface[] $result
     * @return ItemToDeductInterface[]
     */
    public function afterExecute(GetItemsToDeductFromShipment $subject, array $result, Shipment $shipment): array
    {
        $marketplace = [];
        foreach ($shipment->getAllItems() as $shipmentItem) {
            $orderItem = $shipmentItem->getOrderItem();
            $owner = $orderItem?->getParentItem() ?: $orderItem;
            if (!$orderItem || !$owner->getData('mercur_offer_id')) {
                continue;
            }
            $shipped = (float) $shipmentItem->getQty();
            $children = $orderItem->getChildrenItems();
            if ($children === []) {
                $marketplace[(string) $orderItem->getSku()] = ($marketplace[(string) $orderItem->getSku()] ?? 0.0) + $shipped;
                continue;
            }
            $parentQty = max(1.0, (float) $orderItem->getQtyOrdered());
            foreach ($children as $child) {
                $qty = $shipped * (float) $child->getQtyOrdered() / $parentQty;
                $marketplace[(string) $child->getSku()] = ($marketplace[(string) $child->getSku()] ?? 0.0) + $qty;
            }
        }
        if ($marketplace === []) {
            return $result;
        }

        $remaining = [];
        foreach ($result as $item) {
            $left = $item->getQty() - ($marketplace[$item->getSku()] ?? 0.0);
            if ($left > 0) {
                $remaining[] = $this->itemFactory->create(['sku' => $item->getSku(), 'qty' => $left]);
            }
        }

        return $remaining;
    }
}
