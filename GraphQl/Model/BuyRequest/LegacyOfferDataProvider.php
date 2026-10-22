<?php

declare(strict_types=1);

namespace Mercur\GraphQl\Model\BuyRequest;

use Magento\QuoteGraphQl\Model\Cart\BuyRequest\BuyRequestDataProviderInterface;

/**
 * addSimpleProductsToCart and the other per-type mutations.
 */
class LegacyOfferDataProvider implements BuyRequestDataProviderInterface
{
    public function execute(array $cartItemData): array
    {
        $offerId = $cartItemData['data']['mercur_offer_id'] ?? null;

        return $offerId ? ['mercur_offer_id' => (string) $offerId] : [];
    }
}
