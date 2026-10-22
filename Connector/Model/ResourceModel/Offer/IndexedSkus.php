<?php

declare(strict_types=1);

namespace Mercur\Connector\Model\ResourceModel\Offer;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Select;

/**
 * SKUs a website can sell through at least one available seller offer, read
 * from the offer index so stock filters can let them through.
 */
class IndexedSkus
{
    public function __construct(private readonly ResourceConnection $resource)
    {
    }

    public function select(int $websiteId): Select
    {
        return $this->resource->getConnection()->select()
            ->from($this->resource->getTableName('mercur_offer_product_index'), ['product_sku'])
            ->where('website_id = ?', $websiteId)
            ->where('offer_count > 0');
    }

    /**
     * @param int[] $productIds
     * @return int[] the subset the website can sell through an offer
     */
    public function productIdsAmong(array $productIds, int $websiteId): array
    {
        $productIds = array_values(array_unique(array_map('intval', $productIds)));
        if ($productIds === []) {
            return [];
        }
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from(['e' => $this->resource->getTableName('catalog_product_entity')], ['entity_id'])
            ->join(
                ['idx' => $this->resource->getTableName('mercur_offer_product_index')],
                $connection->quoteInto('idx.product_sku = e.sku AND idx.website_id = ?', $websiteId),
                []
            )
            ->where('idx.offer_count > 0')
            ->where('e.entity_id IN (?)', $productIds, \Zend_Db::INT_TYPE);

        return array_map('intval', $connection->fetchCol($select));
    }
}
