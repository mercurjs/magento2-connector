<?php

declare(strict_types=1);

namespace Mercur\GraphQl\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Mercur\Connector\Model\Fulfillment\StatusResolver;
use Mercur\Connector\Model\Order\MarketplaceOrderView;
use Mercur\GraphQl\Model\SellerSummary;

class OrderItemMarketplace implements ResolverInterface
{
    public function __construct(private readonly SellerSummary $sellers)
    {
    }

    public function resolve(Field $field, $context, ResolveInfo $info, ?array $value = null, ?array $args = null): ?array
    {
        $item = $value['model'] ?? null;
        $owner = $item?->getParentItem() ?: $item;
        if (!$owner || !$owner->getData('mercur_offer_id')) {
            return null;
        }
        $status = (string) ($owner->getData('mercur_status') ?: StatusResolver::NOT_FULFILLED);

        return [
            'seller' => $this->sellers->summary((string) $owner->getData('mercur_seller_id')),
            'seller_order_id' => $owner->getData('mercur_order_id'),
            'status' => $status,
            'status_label' => MarketplaceOrderView::statusLabel($status),
        ];
    }
}
