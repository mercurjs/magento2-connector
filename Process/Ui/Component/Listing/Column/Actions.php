<?php

declare(strict_types=1);

namespace Mercur\Process\Ui\Component\Listing\Column;

use Magento\Framework\AuthorizationInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;
use Mercur\Process\Model\Process;

class Actions extends Column
{
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly UrlInterface $urlBuilder,
        private readonly AuthorizationInterface $authorization,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    public function prepareDataSource(array $dataSource): array
    {
        if (!isset($dataSource['data']['items'])) {
            return $dataSource;
        }

        $canRun = $this->authorization->isAllowed('Mercur_Core::process_run');
        $canDelete = $this->authorization->isAllowed('Mercur_Core::process_delete');
        $name = $this->getData('name');

        foreach ($dataSource['data']['items'] as &$item) {
            $id = (int) ($item['id'] ?? 0);
            $status = (string) ($item['status'] ?? '');
            $actions = [
                'view' => [
                    'href' => $this->urlBuilder->getUrl('mercur/process/view', ['id' => $id]),
                    'label' => __('View'),
                ],
            ];
            if ($canRun && in_array($status, Process::RUNNABLE_STATUSES, true)) {
                $actions['run'] = [
                    'href' => $this->urlBuilder->getUrl('mercur/process/run', ['id' => $id]),
                    'label' => __('Run now'),
                    'post' => true,
                ];
            }
            if ($canRun && in_array($status, Process::FINAL_STATUSES, true)) {
                $actions['rerun'] = [
                    'href' => $this->urlBuilder->getUrl('mercur/process/run', ['id' => $id]),
                    'label' => __('Run again'),
                    'post' => true,
                    'confirm' => [
                        'title' => __('Run again'),
                        'message' => __('Queue a new copy of process #%1 and run it now?', $id),
                    ],
                ];
            }
            if ($canRun && in_array($status, Process::ACTIVE_STATUSES, true)) {
                $actions['cancel'] = [
                    'href' => $this->urlBuilder->getUrl('mercur/process/cancel', ['id' => $id]),
                    'label' => __('Stop'),
                    'post' => true,
                    'confirm' => [
                        'title' => __('Stop process'),
                        'message' => __('Stop process #%1?', $id),
                    ],
                ];
            }
            if ($canDelete && $status !== Process::STATUS_PROCESSING) {
                $actions['delete'] = [
                    'href' => $this->urlBuilder->getUrl('mercur/process/delete', ['id' => $id]),
                    'label' => __('Delete'),
                    'post' => true,
                    'confirm' => [
                        'title' => __('Delete process'),
                        'message' => __('Delete process #%1?', $id),
                    ],
                ];
            }
            $item[$name] = $actions;
        }
        unset($item);

        return $dataSource;
    }
}
