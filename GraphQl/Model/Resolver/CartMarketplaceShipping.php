<?php

declare(strict_types=1);

namespace Mercur\GraphQl\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Quote\Model\Quote;

class CartMarketplaceShipping implements ResolverInterface
{
    public function resolve(Field $field, $context, ResolveInfo $info, ?array $value = null, ?array $args = null): ?array
    {
        $quote = $value['model'] ?? null;
        if (!$quote instanceof Quote || $quote->isVirtual()) {
            return null;
        }

        return [
            'value' => (float) $quote->getShippingAddress()->getData('mercur_shipping_amount'),
            'currency' => (string) $quote->getQuoteCurrencyCode(),
        ];
    }
}
