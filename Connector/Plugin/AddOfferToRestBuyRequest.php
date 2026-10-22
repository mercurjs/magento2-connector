<?php

declare(strict_types=1);

namespace Mercur\Connector\Plugin;

use Magento\Framework\DataObject;
use Magento\Quote\Api\Data\CartItemInterface;
use Magento\Quote\Model\Quote\Item\CartItemOptionsProcessor;

/**
 * REST cart items name their offer in `product_option.extension_attributes.mercur_offer_id`.
 */
class AddOfferToRestBuyRequest
{
    public function afterGetBuyRequest(
        CartItemOptionsProcessor $subject,
        DataObject|float|int|null $result,
        $productType,
        CartItemInterface $cartItem
    ): DataObject|float|int|null {
        $offerId = $cartItem->getProductOption()?->getExtensionAttributes()?->getMercurOfferId();
        if (!$offerId) {
            return $result;
        }
        if (!$result instanceof DataObject) {
            $result = new DataObject(['qty' => $result ?? $cartItem->getQty()]);
        }
        $result->setData('mercur_offer_id', (string) $offerId);

        return $result;
    }
}
