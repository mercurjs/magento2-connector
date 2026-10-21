<?php

declare(strict_types=1);

namespace Mercur\Process\Model\ResourceModel\Process;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Mercur\Process\Model\Process;
use Mercur\Process\Model\ResourceModel\Process as ProcessResource;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'id';

    protected function _construct(): void
    {
        $this->_init(Process::class, ProcessResource::class);
    }
}
