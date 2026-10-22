<?php

declare(strict_types=1);

namespace Mercur\Process\Controller\Adminhtml\Process;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Registry;
use Magento\Framework\View\Result\PageFactory;
use Mercur\Process\Model\ProcessFactory;
use Mercur\Process\Model\ResourceModel\Process as ProcessResource;

class View extends AbstractProcessAction implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Mercur_Core::process';

    public const REGISTRY_KEY = 'mercur_current_process';

    public function __construct(
        Action\Context $context,
        ProcessFactory $processFactory,
        ProcessResource $resource,
        private readonly PageFactory $pageFactory,
        private readonly Registry $registry
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

        $this->registry->register(self::REGISTRY_KEY, $process);

        $page = $this->pageFactory->create();
        $page->setActiveMenu('Mercur_Process::process');
        $page->getConfig()->getTitle()->prepend(__('Process #%1 — %2', $process->getId(), $process->getData('name')));

        return $page;
    }
}
