<?php

declare(strict_types=1);

namespace Mercur\Process\Controller\Adminhtml\Process;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Mercur\Process\Model\ProcessFactory;
use Mercur\Process\Model\ResourceModel\Process as ProcessResource;
use Mercur\Process\Model\Runner;
use Mercur\Process\Model\Source\Status;

/**
 * Runs a pending process (`id`) or a task by `code` in the admin request.
 * A finished process is re-queued as a fresh copy and run the same way.
 */
class Run extends AbstractProcessAction implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Mercur_Core::process_run';

    public function __construct(
        Action\Context $context,
        ProcessFactory $processFactory,
        ProcessResource $resource,
        private readonly Runner $runner
    ) {
        parent::__construct($context, $processFactory, $resource);
    }

    public function execute(): ResultInterface
    {
        $code = trim((string) $this->getRequest()->getParam('code'));
        $process = $code !== '' ? null : $this->loadProcess();

        if ($code === '' && $process === null) {
            $this->messageManager->addErrorMessage(__('Choose a task or a process to run.'));

            return $this->redirectToGrid();
        }

        try {
            if ($process === null) {
                $process = $this->runner->runNow($code, ['full' => (bool) $this->getRequest()->getParam('full')]);
            } elseif ($process->isRunnable()) {
                if (!$this->runner->run($process)) {
                    $this->messageManager->addNoticeMessage(__('Process #%1 was picked up by another runner.', $process->getId()));

                    return $this->redirectBack();
                }
            } else {
                $process = $this->runner->runNow($process->getCode(), $process->getParams());
            }
        } catch (\InvalidArgumentException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());

            return $this->redirectToGrid();
        }

        $label = Status::labels()[$process->getStatus()] ?? $process->getStatus();
        if ($process->getStatus() === 'completed') {
            $this->messageManager->addSuccessMessage(__('Process #%1 (%2) finished: %3.', $process->getId(), $process->getCode(), $label));
        } else {
            $this->messageManager->addErrorMessage(__('Process #%1 (%2) ended with status %3.', $process->getId(), $process->getCode(), $label));
        }

        return $this->resultRedirectFactory->create()->setPath('mercur/process/view', ['id' => $process->getId()]);
    }
}
