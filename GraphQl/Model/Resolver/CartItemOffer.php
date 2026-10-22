<?php

declare(strict_types=1);

namespace Mercur\GraphQl\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Mercur\GraphQl\Model\SellerSummary;

class CartItemOffer implements ResolverInterface
{
    public function __construct(private readonly SellerSummary $sellers)
    {
    }

    public function resolve(Field $field, $context, ResolveInfo $info, ?array $value = null, ?array $args = null): ?array
    {
        $item = $value['model'] ?? null;
        if (!$item || !$item->getData('mercur_offer_id')) {
            return null;
        }

        return [
            'offer_id' => (string) $item->getData('mercur_offer_id'),
            'seller' => $this->sellers->summary((string) $item->getData('mercur_seller_id')),
        ];
    }
}
