<?php

declare(strict_types=1);

namespace Mercur\Process\Controller\Adminhtml\Process;

use Magento\Backend\App\Action;
use Magento\Framework\Controller\Result\Redirect;
use Mercur\Process\Model\Process;
use Mercur\Process\Model\ProcessFactory;
use Mercur\Process\Model\ResourceModel\Process as ProcessResource;

abstract class AbstractProcessAction extends Action
{
    public function __construct(
        Action\Context $context,
        protected readonly ProcessFactory $processFactory,
        protected readonly ProcessResource $resource
    ) {
        parent::__construct($context);
    }

    protected function loadProcess(): ?Process
    {
        $id = (int) $this->getRequest()->getParam('id');
        if ($id <= 0) {
            return null;
        }
        $process = $this->processFactory->create();
        $this->resource->load($process, $id);

        return $process->getId() ? $process : null;
    }

    protected function redirectToGrid(): Redirect
    {
        return $this->resultRedirectFactory->create()->setPath('mercur/process/index');
    }

    protected function redirectBack(): Redirect
    {
        $referer = $this->_redirect->getRefererUrl();
        $redirect = $this->resultRedirectFactory->create();

        return $referer ? $redirect->setUrl($referer) : $redirect->setPath('mercur/process/index');
    }
}
