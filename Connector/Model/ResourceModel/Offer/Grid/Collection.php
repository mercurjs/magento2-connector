<?php

declare(strict_types=1);

namespace Mercur\Connector\Model\ResourceModel\Offer\Grid;

use Magento\Framework\View\Element\UiComponent\DataProvider\SearchResult;

/**
 * Offer grid rows with the seller name and the lowest price across websites.
 */
class Collection extends SearchResult
{
    protected function _initSelect(): self
    {
        parent::_initSelect();

        $prices = $this->getConnection()->select()
            ->from($this->getTable('mercur_offer_price'), [
                'offer_id',
                'price' => 'MIN(price)',
                'currency_code' => 'MIN(currency_code)',
                'website_count' => 'COUNT(*)',
            ])
            ->group('offer_id');

        $this->getSelect()
            ->joinLeft(['seller' => $this->getTable('mercur_seller')], 'seller.seller_id = main_table.seller_id', ['seller_name' => 'seller.name'])
            ->joinLeft(['offer_price' => $prices], 'offer_price.offer_id = main_table.offer_id', ['price', 'currency_code', 'website_count']);

        $this->addFilterToMap('offer_id', 'main_table.offer_id');
        $this->addFilterToMap('seller_id', 'main_table.seller_id');
        $this->addFilterToMap('seller_name', 'seller.name');
        $this->addFilterToMap('price', 'offer_price.price');
        $this->addFilterToMap('updated_at', 'main_table.updated_at');

        return $this;
    }
}
