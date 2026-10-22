<?php

declare(strict_types=1);

namespace Mercur\GraphQl\Plugin;

use Magento\Quote\Model\Cart\Data\CartItemFactory;
use Mercur\GraphQl\Model\BuyRequest\OfferEnteredOption;

class MapOfferToEnteredOption
{
    public function beforeCreate(CartItemFactory $subject, array $data): array
    {
        if (!empty($data['mercur_offer_id'])) {
            $data['entered_options'][] = ['uid' => OfferEnteredOption::uid(), 'value' => (string) $data['mercur_offer_id']];
            unset($data['mercur_offer_id']);
        }

        return [$data];
    }
}
