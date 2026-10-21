<?php

declare(strict_types=1);

namespace Mercur\Frontend\Controller\Shipping;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Mercur\Connector\Model\Checkout\ShadowCart;
use Mercur\Connector\Model\ResourceModel\Seller\CollectionFactory;

/**
 * GET /mercur/shipping/options lists the shipping options Mercur quotes per
 * seller for the current cart, with the chosen one marked.
 */
class Options implements HttpGetActionInterface
{
    public function __construct(
        private readonly Session $session,
        private readonly ShadowCart $shadowCart,
        private readonly CollectionFactory $sellers,
        private readonly JsonFactory $jsonFactory
    ) {
    }

    public function execute(): ResultInterface
    {
        $result = $this->jsonFactory->create();
        $quote = $this->session->getQuote();
        if (!$this->shadowCart->hasMarketplaceItems($quote)) {
            return $result->setData(['sellers' => []]);
        }

        $options = $this->shadowCart->shippingOptions($quote);
        $chosen = $this->shadowCart->chosenShippingMethods($quote);
        $names = [];
        foreach ($this->sellers->create()->addFieldToFilter('seller_id', ['in' => array_keys($options)]) as $seller) {
            $names[$seller->getSellerId()] = $seller->getName();
        }

        $payload = [];
        foreach ($options as $sellerId => $sellerOptions) {
            $payload[] = [
                'seller_id' => $sellerId,
                'seller_name' => $names[$sellerId] ?? $sellerId,
                'options' => array_map(static fn ($o) => [
                    'id' => $o['id'],
                    'name' => $o['name'] ?? '',
                    'amount' => (float) ($o['amount'] ?? 0),
                    'chosen' => ($chosen[$sellerId] ?? null) === $o['id'],
                ], $sellerOptions),
            ];
        }

        return $result->setData(['sellers' => $payload]);
    }
}
