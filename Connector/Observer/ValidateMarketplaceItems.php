<?php

declare(strict_types=1);

namespace Mercur\Connector\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Quote\Model\Quote;
use Mercur\Connector\Model\Checkout\MarketplaceItemValidator;
use Mercur\Core\Model\Config;

class ValidateMarketplaceItems implements ObserverInterface
{
    public function __construct(
        private readonly MarketplaceItemValidator $validator,
        private readonly Config $config
    ) {
    }

    public function execute(Observer $observer): void
    {
        $quote = $observer->getEvent()->getData('quote');
        if ($quote instanceof Quote && $this->config->isEnabled((int) $quote->getStoreId())) {
            $this->validator->validate($quote);
        }
    }
}
