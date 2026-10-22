<?php

declare(strict_types=1);

namespace Mercur\Connector\Controller\Adminhtml\Offer;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Mercur_Core::offers';

    public function __construct(
        Action\Context $context,
        private readonly PageFactory $pageFactory
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $page = $this->pageFactory->create();
        $page->setActiveMenu('Mercur_Connector::offers');
        $page->getConfig()->getTitle()->prepend(__('Marketplace offers'));

        return $page;
    }
}
