<?php

declare(strict_types=1);

namespace Mercur\Process\Controller\Adminhtml\Process;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultInterface;

class Delete extends AbstractProcessAction implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Mercur_Core::process_delete';

    public function execute(): ResultInterface
    {
        $process = $this->loadProcess();
        if ($process === null) {
            $this->messageManager->addErrorMessage(__('This process no longer exists.'));
        } elseif ($process->getStatus() === 'processing') {
            $this->messageManager->addErrorMessage(__('Process #%1 is running; stop it first.', $process->getId()));
        } else {
            $this->resource->delete($process);
            $this->messageManager->addSuccessMessage(__('Process #%1 was deleted.', $process->getId()));
        }

        return $this->redirectToGrid();
    }
}
