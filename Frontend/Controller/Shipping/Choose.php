<?php

declare(strict_types=1);

namespace Mercur\Frontend\Controller\Shipping;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Mercur\Connector\Model\Checkout\ShadowCart;

/**
 * POST /mercur/shipping/choose with seller_id and option_id picks a seller's
 * shipping option for the current cart; totals recollect on the next request.
 */
class Choose implements HttpPostActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly Session $session,
        private readonly ShadowCart $shadowCart,
        private readonly JsonFactory $jsonFactory
    ) {
    }

    public function execute(): ResultInterface
    {
        $result = $this->jsonFactory->create();
        $sellerId = (string) $this->request->getParam('seller_id', '');
        $optionId = (string) $this->request->getParam('option_id', '');
        if ($sellerId === '' || $optionId === '') {
            return $result->setHttpResponseCode(400)->setData(['error' => 'seller_id and option_id are required']);
        }

        $quote = $this->session->getQuote();
        try {
            $this->shadowCart->chooseShippingMethod($quote, $sellerId, $optionId);
        } catch (LocalizedException $e) {
            return $result->setHttpResponseCode(400)->setData(['error' => $e->getMessage()]);
        }
        $quote->setTotalsCollectedFlag(false);

        return $result->setData(['ok' => true]);
    }
}
