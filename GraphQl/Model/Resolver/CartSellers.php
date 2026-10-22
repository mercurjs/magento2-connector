<?php

declare(strict_types=1);

namespace Mercur\GraphQl\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Quote\Model\Quote;
use Mercur\Connector\Model\Checkout\SellerShippingView;
use Mercur\GraphQl\Model\SellerSummary;

class CartSellers implements ResolverInterface
{
    public function __construct(
        private readonly SellerShippingView $view,
        private readonly SellerSummary $sellers
    ) {
    }

    public function resolve(Field $field, $context, ResolveInfo $info, ?array $value = null, ?array $args = null): array
    {
        $quote = $value['model'] ?? null;
        if (!$quote instanceof Quote) {
            return [];
        }

        return array_map(fn (array $group) => [
            'seller' => $this->sellers->summary($group['seller_id']) ?? ['id' => $group['seller_id'], 'name' => $group['seller_name']],
            'items' => array_map(static fn (array $item) => [
                'item_id' => $item['item_id'],
                'sku' => $item['sku'],
                'name' => $item['name'],
                'quantity' => $item['qty'],
            ], $group['items']),
            'shipping_options' => array_map(static fn (array $option) => [
                'id' => $option['id'],
                'name' => $option['name'],
                'amount' => $option['amount'],
                'selected' => $option['chosen'],
                'estimated_delivery' => $option['estimated_delivery'],
            ], $group['options']),
            'estimated_delivery' => $group['estimated_delivery'],
        ], $this->view->build($quote));
    }
}
