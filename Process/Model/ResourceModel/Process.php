<?php

declare(strict_types=1);

namespace Mercur\Process\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Process extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('mercur_process', 'id');
    }
}
