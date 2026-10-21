<?php

declare(strict_types=1);

namespace Mercur\OfferIndexer\Model\ResourceModel;

use Magento\Framework\App\ResourceConnection;

class ProductIndex
{
    public function __construct(private readonly ResourceConnection $resource)
    {
    }

    public function replace(array $rows): void
    {
        if ($rows === []) {
            return;
        }
        $this->resource->getConnection()->insertOnDuplicate($this->table(), $rows);
    }

    public function deleteSkus(array $skus, int $websiteId): void
    {
        if ($skus === []) {
            return;
        }
        $this->resource->getConnection()->delete($this->table(), [
            'product_sku IN (?)' => $skus,
            'website_id = ?' => $websiteId,
        ]);
    }

    public function truncate(): void
    {
        $this->resource->getConnection()->truncateTable($this->table());
    }

    public function get(string $sku, int $websiteId): ?array
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()->from($this->table())
            ->where('product_sku = ?', $sku)
            ->where('website_id = ?', $websiteId);
        $row = $connection->fetchRow($select);

        return $row ?: null;
    }

    private function table(): string
    {
        return $this->resource->getTableName('mercur_offer_product_index');
    }
}
