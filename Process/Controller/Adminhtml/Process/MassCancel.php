<?php

declare(strict_types=1);

namespace Mercur\Process\Controller\Adminhtml\Process;

use Mercur\Process\Model\Process;
use Mercur\Process\Model\ResourceModel\Process\Collection;

class MassCancel extends AbstractMassAction
{
    public const ADMIN_RESOURCE = 'Mercur_Core::process_run';

    protected function apply(Collection $collection): void
    {
        $ids = [];
        foreach ($collection as $process) {
            if ($process->isActive()) {
                $ids[] = (int) $process->getId();
            }
        }

        $stopped = $this->resource->setStatus($ids, Process::STATUS_STOPPED, true);
        $this->messageManager->addSuccessMessage(__('%1 process(es) were stopped.', $stopped));
    }
}
