<?php

declare(strict_types=1);

namespace Mercur\Process\Controller\Adminhtml\Process;

use Mercur\Process\Model\Process;
use Mercur\Process\Model\ResourceModel\Process\Collection;

class MassDelete extends AbstractMassAction
{
    public const ADMIN_RESOURCE = 'Mercur_Core::process_delete';

    protected function apply(Collection $collection): void
    {
        $ids = [];
        $skipped = 0;
        foreach ($collection as $process) {
            if ($process->getStatus() === Process::STATUS_PROCESSING) {
                $skipped++;
                continue;
            }
            $ids[] = (int) $process->getId();
        }

        $deleted = $this->resource->deleteByIds($ids);
        $this->messageManager->addSuccessMessage(__('%1 process(es) were deleted.', $deleted));
        if ($skipped > 0) {
            $this->messageManager->addNoticeMessage(__('%1 running process(es) were left in place.', $skipped));
        }
    }
}
