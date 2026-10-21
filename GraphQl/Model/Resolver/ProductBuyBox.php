<?php

declare(strict_types=1);

namespace Mercur\GraphQl\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Mercur\OfferIndexer\Model\BuyBox;

class ProductBuyBox implements ResolverInterface
{
    public function __construct(private readonly BuyBox $buyBox)
    {
    }

    public function resolve(Field $field, $context, ResolveInfo $info, ?array $value = null, ?array $args = null): ?array
    {
        $product = $value['model'] ?? null;
        if (!$product) {
            return null;
        }

        $websiteId = (int) $context->getExtensionAttributes()->getStore()->getWebsiteId();
        $summary = $this->buyBox->summary((string) $product->getSku(), $websiteId);
        if ($summary === null) {
            return null;
        }

        return [
            'best_offer_id' => $summary['best_offer_id'],
            'best_seller_id' => $summary['best_seller_id'],
            'best_price' => $summary['best_price'] === null ? null : (float) $summary['best_price'],
            'min_price' => $summary['min_price'] === null ? null : (float) $summary['min_price'],
            'max_price' => $summary['max_price'] === null ? null : (float) $summary['max_price'],
            'offer_count' => (int) $summary['offer_count'],
            'operator_available' => (bool) $summary['operator_available'],
        ];
    }
}
