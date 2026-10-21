<?php

declare(strict_types=1);

namespace Mercur\Connector\Model\ResourceModel\Offer;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Mercur\Connector\Model\Offer;
use Mercur\Connector\Model\ResourceModel\Offer as OfferResource;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'offer_id';

    protected function _construct(): void
    {
        $this->_init(Offer::class, OfferResource::class);
    }

    public function addSkuFilter(string|array $sku): self
    {
        $this->addFieldToFilter('product_sku', ['in' => (array) $sku]);

        return $this;
    }

    public function addActiveFilter(): self
    {
        $this->addFieldToFilter('active', 1);

        return $this;
    }

    public function joinSeller(): self
    {
        $this->getSelect()->joinLeft(
            ['seller' => $this->getTable('mercur_seller')],
            'seller.seller_id = main_table.seller_id',
            ['seller_name' => 'seller.name', 'seller_handle' => 'seller.handle', 'seller_premium' => 'seller.premium']
        );

        return $this;
    }
}
