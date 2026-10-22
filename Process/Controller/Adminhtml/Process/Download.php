<?php

declare(strict_types=1);

namespace Mercur\Process\Controller\Adminhtml\Process;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Mercur\Process\Model\ProcessFactory;
use Mercur\Process\Model\ResourceModel\Process as ProcessResource;

class Download extends AbstractProcessAction implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Mercur_Core::process';

    public function __construct(
        Action\Context $context,
        ProcessFactory $processFactory,
        ProcessResource $resource,
        private readonly FileFactory $fileFactory
    ) {
        parent::__construct($context, $processFactory, $resource);
    }

    public function execute(): ResultInterface|ResponseInterface
    {
        $process = $this->loadProcess();
        if ($process === null) {
            $this->messageManager->addErrorMessage(__('This process no longer exists.'));

            return $this->redirectToGrid();
        }

        $content = sprintf("# %s (#%d) %s\n\n", $process->getCode(), $process->getId(), $process->getStatus())
            . (string) $process->getData('output');

        return $this->fileFactory->create(
            sprintf('mercur-process-%d.log', $process->getId()),
            $content,
            'var',
            'text/plain'
        );
    }
}
