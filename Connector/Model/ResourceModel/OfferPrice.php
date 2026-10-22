<?php

declare(strict_types=1);

namespace Mercur\Connector\Model\ResourceModel;

use Magento\Framework\App\ResourceConnection;

class OfferPrice
{
    public function __construct(private readonly ResourceConnection $resource)
    {
    }

    public function upsert(array $rows): void
    {
        if ($rows === []) {
            return;
        }
        $this->resource->getConnection()->insertOnDuplicate($this->table(), $rows);
    }

    /**
     * @param string[] $offerIds
     */
    public function deleteForOffers(array $offerIds, ?int $websiteId = null): void
    {
        if ($offerIds === []) {
            return;
        }
        $where = ['offer_id IN (?)' => $offerIds];
        if ($websiteId !== null) {
            $where['website_id = ?'] = $websiteId;
        }
        $this->resource->getConnection()->delete($this->table(), $where);
    }

    public function deleteOrphans(): void
    {
        $connection = $this->resource->getConnection();
        $offers = $this->resource->getTableName('mercur_offer');
        $connection->delete(
            $this->table(),
            ['offer_id NOT IN (?)' => $connection->select()->from($offers, ['offer_id'])]
        );
    }

    private function table(): string
    {
        return $this->resource->getTableName('mercur_offer_price');
    }
}
