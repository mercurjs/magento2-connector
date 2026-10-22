<?php

declare(strict_types=1);

namespace Mercur\Process\Controller\Adminhtml\Process;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Mercur\Process\Model\HistoryCleaner;

class ClearHistory extends Action implements HttpGetActionInterface, HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Mercur_Core::process_delete';

    public function __construct(
        Action\Context $context,
        private readonly HistoryCleaner $cleaner
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        $result = $this->cleaner->clean();
        $this->messageManager->addSuccessMessage(__('%1 finished process(es) were deleted.', $result['processes']));

        return $this->resultRedirectFactory->create()->setPath('mercur/process/index');
    }
}
