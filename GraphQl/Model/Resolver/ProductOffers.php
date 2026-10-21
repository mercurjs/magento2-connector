<?php

declare(strict_types=1);

namespace Mercur\GraphQl\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Mercur\OfferIndexer\Model\BuyBox;

class ProductOffers implements ResolverInterface
{
    public function __construct(private readonly BuyBox $buyBox)
    {
    }

    public function resolve(Field $field, $context, ResolveInfo $info, ?array $value = null, ?array $args = null): array
    {
        $product = $value['model'] ?? null;
        if (!$product) {
            return [];
        }

        $sku = (string) $product->getSku();
        $websiteId = (int) $context->getExtensionAttributes()->getStore()->getWebsiteId();
        $best = $this->buyBox->summary($sku, $websiteId)['best_offer_id'] ?? null;

        $result = [];
        foreach ($this->buyBox->offers($sku) as $offer) {
            $result[] = [
                'id' => $offer->getOfferId(),
                'seller_id' => $offer->getSellerId(),
                'seller_name' => $offer->getData('seller_name'),
                'seller_premium' => (bool) $offer->getData('seller_premium'),
                'price' => $offer->getPrice(),
                'origin_price' => $offer->getOriginPrice(),
                'currency_code' => $offer->getData('currency_code'),
                'quantity' => $offer->getQuantity(),
                'leadtime_to_ship' => $offer->getData('leadtime_to_ship') === null ? null : (int) $offer->getData('leadtime_to_ship'),
                'condition_code' => $offer->getData('condition_code'),
                'is_best' => $best === $offer->getOfferId(),
            ];
        }

        return $result;
    }
}
