<?php

declare(strict_types=1);

namespace Mercur\Frontend\Controller\Seller;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\ForwardFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Registry;
use Magento\Framework\View\Result\PageFactory;
use Mercur\Connector\Model\ResourceModel\Seller\CollectionFactory;
use Mercur\Connector\Model\Seller;
use Mercur\Core\Model\Config;

class View implements HttpGetActionInterface
{
    public const REGISTRY_KEY = 'mercur_current_seller';

    public function __construct(
        private readonly RequestInterface $request,
        private readonly CollectionFactory $sellers,
        private readonly Registry $registry,
        private readonly PageFactory $pageFactory,
        private readonly ForwardFactory $forwardFactory,
        private readonly Config $config
    ) {
    }

    public function execute(): ResultInterface
    {
        $handle = (string) $this->request->getParam('handle', '');
        $seller = $this->sellers->create()->addFieldToFilter('handle', $handle)->setPageSize(1)->getFirstItem();
        if (!$this->config->isEnabled() || !$seller instanceof Seller || $seller->getSellerId() === '' || !$seller->isSelling()) {
            return $this->forwardFactory->create()->forward('noroute');
        }

        $this->registry->register(self::REGISTRY_KEY, $seller);
        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->set($seller->getName());
        $description = trim(strip_tags((string) $seller->getData('description')));
        if ($description !== '') {
            $page->getConfig()->setDescription(mb_substr($description, 0, 255));
        }

        return $page;
    }
}
