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
        $this->addFieldToFilter('main_table.product_sku', ['in' => (array) $sku]);

        return $this;
    }

    public function addActiveFilter(): self
    {
        $this->addFieldToFilter('main_table.active', 1);

        return $this;
    }

    /**
     * Joins the price of the given website; offers without one are not sold there.
     */
    public function addWebsitePrice(int $websiteId): self
    {
        $this->getSelect()->join(
            ['offer_price' => $this->getTable('mercur_offer_price')],
            $this->getConnection()->quoteInto(
                'offer_price.offer_id = main_table.offer_id AND offer_price.website_id = ?',
                $websiteId
            ),
            ['price', 'origin_price', 'currency_code', 'price_ranges', 'website_id']
        );

        return $this;
    }

    public function joinSeller(): self
    {
        $this->getSelect()->joinLeft(
            ['seller' => $this->getTable('mercur_seller')],
            'seller.seller_id = main_table.seller_id',
            [
                'seller_name' => 'seller.name',
                'seller_handle' => 'seller.handle',
                'seller_premium' => 'seller.premium',
                'seller_logo' => 'seller.logo',
                'seller_default_leadtime_to_ship' => 'seller.default_leadtime_to_ship',
            ]
        );

        return $this;
    }
}
