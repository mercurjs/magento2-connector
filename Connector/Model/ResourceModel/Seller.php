<?php

declare(strict_types=1);

namespace Mercur\Connector\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Seller extends AbstractDb
{
    protected $_isPkAutoIncrement = false;

    protected function _construct(): void
    {
        $this->_init('mercur_seller', 'seller_id');
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
        $this->getConnection()->delete($this->getMainTable(), ['seller_id IN (?)' => $ids]);
    }
}
