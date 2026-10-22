<?php

declare(strict_types=1);

namespace Mercur\OfferIndexer\Model\ResourceModel;

use Magento\Framework\App\ResourceConnection;

class SkuResolver
{
    public function __construct(private readonly ResourceConnection $resource)
    {
    }

    /**
     * @param int[] $productIds
     * @return array<int, string> sku keyed by product id
     */
    public function skusForProductIds(array $productIds): array
    {
        $productIds = array_values(array_unique(array_filter($productIds)));
        if ($productIds === []) {
            return [];
        }
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from($this->resource->getTableName('catalog_product_entity'), ['entity_id', 'sku'])
            ->where('entity_id IN (?)', $productIds, \Zend_Db::INT_TYPE);

        $result = [];
        foreach ($connection->fetchPairs($select) as $id => $sku) {
            $result[(int) $id] = (string) $sku;
        }

        return $result;
    }

    /**
     * @param string[] $skus
     * @return array<string, int> product id keyed by sku
     */
    public function productIdsForSkus(array $skus): array
    {
        $skus = array_values(array_unique(array_filter($skus, 'strlen')));
        if ($skus === []) {
            return [];
        }
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from($this->resource->getTableName('catalog_product_entity'), ['sku', 'entity_id'])
            ->where('sku IN (?)', $skus);

        $result = [];
        foreach ($connection->fetchPairs($select) as $sku => $id) {
            $result[(string) $sku] = (int) $id;
        }

        return $result;
    }
}
