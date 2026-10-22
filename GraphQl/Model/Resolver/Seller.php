<?php

declare(strict_types=1);

namespace Mercur\GraphQl\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Mercur\Connector\Model\ResourceModel\Seller\CollectionFactory;
use Mercur\Connector\Model\Seller as SellerModel;
use Mercur\GraphQl\Model\SellerSummary;

class Seller implements ResolverInterface
{
    public function __construct(
        private readonly CollectionFactory $sellers,
        private readonly SellerSummary $summary
    ) {
    }

    public function resolve(Field $field, $context, ResolveInfo $info, ?array $value = null, ?array $args = null): ?array
    {
        $seller = $this->sellers->create()->addFieldToFilter('handle', (string) ($args['handle'] ?? ''))->setPageSize(1)->getFirstItem();
        if (!$seller instanceof SellerModel || $seller->getSellerId() === '' || !$seller->isSelling()) {
            return null;
        }

        return $this->summary->profile($seller);
    }
}
