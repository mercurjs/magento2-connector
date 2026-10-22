<?php

declare(strict_types=1);

namespace Mercur\Frontend\Controller\Shipping;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Mercur\Connector\Model\Checkout\SellerShippingView;

/**
 * GET /mercur/shipping/options — the cart's seller groups with their shipping options.
 */
class Options implements HttpGetActionInterface
{
    public function __construct(
        private readonly Session $session,
        private readonly SellerShippingView $view,
        private readonly JsonFactory $jsonFactory
    ) {
    }

    public function execute(): ResultInterface
    {
        return $this->jsonFactory->create()->setData(['sellers' => $this->view->build($this->session->getQuote())]);
    }
}
