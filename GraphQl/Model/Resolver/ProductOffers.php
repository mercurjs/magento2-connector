<?php

declare(strict_types=1);

namespace Mercur\GraphQl\Model\Resolver;

use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Mercur\Connector\Model\Delivery\DeliveryEstimate;
use Mercur\Connector\Model\OfferRepository;
use Mercur\GraphQl\Model\SellerSummary;
use Mercur\OfferIndexer\Model\BuyBox;

class ProductOffers implements ResolverInterface
{
    public function __construct(
        private readonly OfferRepository $offers,
        private readonly BuyBox $buyBox,
        private readonly DeliveryEstimate $delivery,
        private readonly SellerSummary $sellers
    ) {
    }

    public function resolve(Field $field, $context, ResolveInfo $info, ?array $value = null, ?array $args = null): array
    {
        $product = $value['model'] ?? null;
        if (!$product) {
            return [];
        }
        $store = $context->getExtensionAttributes()->getStore();
        $websiteId = (int) $store->getWebsiteId();
        $storeId = (int) $store->getId();

        $skus = [(string) $product->getSku()];
        if ($product->getTypeId() === Configurable::TYPE_CODE) {
            $skus = array_map(static fn ($child) => (string) $child->getSku(), $product->getTypeInstance()->getUsedProducts($product));
        }

        $result = [];
        foreach ($this->offers->getActiveForSkus($skus, $websiteId) as $offer) {
            $best = $this->buyBox->summary($offer->getProductSku(), $websiteId)['best_offer_id'] ?? null;
            $result[] = [
                'id' => $offer->getOfferId(),
                'sku' => $offer->getProductSku(),
                'seller_id' => $offer->getSellerId(),
                'seller_name' => $offer->getSellerName(),
                'seller_premium' => (bool) $offer->getData('seller_premium'),
                'seller' => $this->sellers->summary($offer->getSellerId()),
                'price' => $offer->getPrice(),
                'origin_price' => $offer->getOriginPrice(),
                'price_ranges' => $offer->getPriceRanges(),
                'currency_code' => $offer->getData('currency_code'),
                'quantity' => $offer->getQuantity(),
                'leadtime_to_ship' => $offer->getLeadtimeToShip(),
                'estimated_delivery' => $this->delivery->estimateDates($offer->getLeadtimeToShip(), $storeId),
                'condition_code' => $offer->getData('condition_code'),
                'condition_label' => $offer->getConditionLabel(),
                'is_best' => $best === $offer->getOfferId(),
            ];
        }

        return $result;
    }
}
