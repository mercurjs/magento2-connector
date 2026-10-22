<?php

declare(strict_types=1);

namespace Mercur\Process\Block\Adminhtml\Process;

use Magento\Backend\Block\Template;
use Mercur\Process\Model\TaskPool;

class Toolbar extends Template
{
    protected $_template = 'Mercur_Process::process/toolbar.phtml';

    public function __construct(
        Template\Context $context,
        private readonly TaskPool $tasks,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @return array<string, string> code => name
     */
    public function getTasks(): array
    {
        $tasks = [];
        foreach ($this->tasks->all() as $code => $task) {
            $tasks[$code] = $task->getName();
        }
        ksort($tasks);

        return $tasks;
    }

    public function getRunUrl(): string
    {
        return $this->getUrl('mercur/process/run');
    }

    public function getClearHistoryUrl(): string
    {
        return $this->getUrl('mercur/process/clearHistory');
    }

    public function canRun(): bool
    {
        return $this->_authorization->isAllowed('Mercur_Core::process_run');
    }

    public function canDelete(): bool
    {
        return $this->_authorization->isAllowed('Mercur_Core::process_delete');
    }
}
