<?php

declare(strict_types=1);

namespace Mercur\GraphQl\Model\BuyRequest;

/**
 * addProductsToCart only keeps known fields, so `mercur_offer_id` travels as
 * an entered option under this uid.
 */
final class OfferEnteredOption
{
    public static function uid(): string
    {
        return base64_encode('mercur_offer');
    }
}
