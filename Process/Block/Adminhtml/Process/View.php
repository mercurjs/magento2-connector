<?php

declare(strict_types=1);

namespace Mercur\Process\Block\Adminhtml\Process;

use Magento\Backend\Block\Template;
use Magento\Framework\Registry;
use Mercur\Process\Controller\Adminhtml\Process\View as ViewAction;
use Mercur\Process\Model\Process;
use Mercur\Process\Model\ResourceModel\Process\CollectionFactory;
use Mercur\Process\Model\Source\Status;

class View extends Template
{
    protected $_template = 'Mercur_Process::process/view.phtml';

    public function __construct(
        Template\Context $context,
        private readonly Registry $registry,
        private readonly CollectionFactory $collectionFactory,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getProcess(): Process
    {
        $process = $this->registry->registry(ViewAction::REGISTRY_KEY);
        if (!$process instanceof Process) {
            throw new \LogicException('No process registered for the view page');
        }

        return $process;
    }

    /**
     * Phrase objects cannot be array keys, so the labels are rendered here.
     *
     * @return array<string, string> label => value
     */
    public function getRows(): array
    {
        $process = $this->getProcess();
        $duration = $process->getData('duration');

        return [
            (string) __('Task') => $process->getCode(),
            (string) __('Name') => (string) $process->getData('name'),
            (string) __('Status') => $this->getStatusLabel($process->getStatus()),
            (string) __('Attempts') => $process->getAttempts() . ' / ' . $process->getMaxAttempts(),
            (string) __('Created') => $this->formatStamp($process->getData('created_at')),
            (string) __('Started') => $this->formatStamp($process->getData('started_at')),
            (string) __('Finished') => $this->formatStamp($process->getData('finished_at')),
            (string) __('Next run') => $this->formatStamp($process->getData('next_run_at')),
            (string) __('Duration') => $duration !== null ? $duration . ' s' : '—',
            (string) __('Runner') => (string) ($process->getData('locked_by') ?: '—'),
        ];
    }

    public function getStatusLabel(string $status): string
    {
        $label = Status::labels()[$status] ?? null;

        return $label === null ? $status : (string) $label;
    }

    public function getParamsJson(): string
    {
        $params = $this->getProcess()->getParams();

        return $params === [] ? '' : (string) json_encode($params, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @return Process[]
     */
    public function getChildren(): array
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('parent_id', (int) $this->getProcess()->getId())->setOrder('id', 'ASC');

        return array_values($collection->getItems());
    }

    public function getParentUrl(): ?string
    {
        $parentId = $this->getProcess()->getParentId();

        return $parentId === null ? null : $this->getUrl('mercur/process/view', ['id' => $parentId]);
    }

    public function getActionUrl(string $action): string
    {
        return $this->getUrl('mercur/process/' . $action, ['id' => $this->getProcess()->getId()]);
    }

    public function canRun(): bool
    {
        return $this->_authorization->isAllowed('Mercur_Core::process_run');
    }

    public function canDelete(): bool
    {
        return $this->_authorization->isAllowed('Mercur_Core::process_delete');
    }

    public function formatStamp(?string $value): string
    {
        return $value ? $this->formatDate($value, \IntlDateFormatter::MEDIUM, true) : '—';
    }
}
