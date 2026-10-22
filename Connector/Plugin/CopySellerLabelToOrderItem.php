<?php

declare(strict_types=1);

namespace Mercur\Connector\Plugin;

use Magento\Framework\Serialize\Serializer\Json;
use Magento\Quote\Model\Quote\Item\AbstractItem;
use Magento\Quote\Model\Quote\Item\ToOrderItem;
use Magento\Sales\Api\Data\OrderItemInterface;

/**
 * Magento keeps `additional_options` on the cart line only; this carries the
 * "Sold by" label onto the order line so order views, emails and PDFs show it.
 */
class CopySellerLabelToOrderItem
{
    public function __construct(private readonly Json $json)
    {
    }

    public function afterConvert(ToOrderItem $subject, OrderItemInterface $orderItem, AbstractItem $item): OrderItemInterface
    {
        $option = $item->getOptionByCode('additional_options');
        if (!$option || !$item->getData('mercur_offer_id')) {
            return $orderItem;
        }

        $options = $orderItem->getProductOptions() ?: [];
        $options['additional_options'] = $this->json->unserialize((string) $option->getValue());
        $orderItem->setProductOptions($options);

        return $orderItem;
    }
}
