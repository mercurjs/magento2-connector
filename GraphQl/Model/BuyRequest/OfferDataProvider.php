<?php

declare(strict_types=1);

namespace Mercur\GraphQl\Model\BuyRequest;

use Magento\Quote\Model\Cart\BuyRequest\BuyRequestDataProviderInterface;
use Magento\Quote\Model\Cart\Data\CartItem;

/**
 * addProductsToCart: puts the offer into the buy request.
 */
class OfferDataProvider implements BuyRequestDataProviderInterface
{
    public function execute(CartItem $cartItem): array
    {
        foreach ($cartItem->getEnteredOptions() ?? [] as $option) {
            if ($option->getUid() === OfferEnteredOption::uid()) {
                return ['mercur_offer_id' => (string) $option->getValue()];
            }
        }

        return [];
    }
}
