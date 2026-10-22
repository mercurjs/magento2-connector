<?php

declare(strict_types=1);

namespace Mercur\Process\Controller\Adminhtml\Process;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Ui\Component\MassAction\Filter;
use Mercur\Process\Model\ResourceModel\Process as ProcessResource;
use Mercur\Process\Model\ResourceModel\Process\Collection;
use Mercur\Process\Model\ResourceModel\Process\CollectionFactory;

abstract class AbstractMassAction extends Action implements HttpPostActionInterface
{
    public function __construct(
        Action\Context $context,
        protected readonly Filter $filter,
        protected readonly CollectionFactory $collectionFactory,
        protected readonly ProcessResource $resource
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        /** @var Collection $collection */
        $collection = $this->filter->getCollection($this->collectionFactory->create());
        $this->apply($collection);

        return $this->resultRedirectFactory->create()->setPath('mercur/process/index');
    }

    abstract protected function apply(Collection $collection): void;
}
