<?php

declare(strict_types=1);

namespace Mercur\Process\Controller\Adminhtml\Process;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Mercur\Process\Model\ProcessFactory;
use Mercur\Process\Model\ResourceModel\Process as ProcessResource;
use Mercur\Process\Model\Runner;

class Cancel extends AbstractProcessAction implements HttpPostActionInterface
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
        $process = $this->loadProcess();
        if ($process === null) {
            $this->messageManager->addErrorMessage(__('This process no longer exists.'));

            return $this->redirectToGrid();
        }

        if ($this->runner->cancel($process)) {
            $this->messageManager->addSuccessMessage(__('Process #%1 was stopped.', $process->getId()));
        } else {
            $this->messageManager->addNoticeMessage(__('Process #%1 had already finished.', $process->getId()));
        }

        return $this->redirectBack();
    }
}
