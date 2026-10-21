<?php

declare(strict_types=1);

namespace Mercur\Connector\Model\ResourceModel\Seller;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Mercur\Connector\Model\ResourceModel\Seller as SellerResource;
use Mercur\Connector\Model\Seller;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'seller_id';

    protected function _construct(): void
    {
        $this->_init(Seller::class, SellerResource::class);
    }
}
