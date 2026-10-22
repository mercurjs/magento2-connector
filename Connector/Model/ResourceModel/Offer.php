<?php

declare(strict_types=1);

namespace Mercur\Connector\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Offer extends AbstractDb
{
    protected $_isPkAutoIncrement = false;

    protected function _construct(): void
    {
        $this->_init('mercur_offer', 'offer_id');
    }

    public function upsert(array $rows): void
    {
        if ($rows === []) {
            return;
        }
        $this->getConnection()->insertOnDuplicate($this->getMainTable(), $rows);
    }

    public function deleteByIds(array $ids): void
    {
        if ($ids === []) {
            return;
        }
        $this->getConnection()->delete($this->getMainTable(), ['offer_id IN (?)' => $ids]);
    }

    public function deleteNotIn(array $keepIds): void
    {
        if ($keepIds === []) {
            return;
        }
        $this->getConnection()->delete($this->getMainTable(), ['offer_id NOT IN (?)' => $keepIds]);
    }

    /**
     * @param string[] $offerIds
     * @return string[] distinct SKUs the offers sell
     */
    public function skusForIds(array $offerIds): array
    {
        if ($offerIds === []) {
            return [];
        }
        $connection = $this->getConnection();
        $select = $connection->select()
            ->distinct()
            ->from($this->getMainTable(), ['product_sku'])
            ->where('offer_id IN (?)', $offerIds);

        return array_map('strval', $connection->fetchCol($select));
    }

    /**
     * @param string[] $sellerIds
     * @return string[] distinct SKUs the sellers offer
     */
    public function skusForSellers(array $sellerIds): array
    {
        if ($sellerIds === []) {
            return [];
        }
        $connection = $this->getConnection();
        $select = $connection->select()
            ->distinct()
            ->from($this->getMainTable(), ['product_sku'])
            ->where('seller_id IN (?)', $sellerIds);

        return array_map('strval', $connection->fetchCol($select));
    }

    /**
     * Marks every offer of the given sellers active or inactive, which the
     * seller sync calls when a seller changes status or enters a closure.
     */
    public function setActiveForSellers(array $sellerIds, bool $active): void
    {
        if ($sellerIds === []) {
            return;
        }
        $this->getConnection()->update(
            $this->getMainTable(),
            ['active' => (int) $active],
            ['seller_id IN (?)' => $sellerIds]
        );
    }
}
