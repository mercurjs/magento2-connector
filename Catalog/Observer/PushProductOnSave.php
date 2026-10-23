<?php

declare(strict_types=1);

namespace Mercur\Catalog\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Mercur\Catalog\Model\CatalogConfig;
use Mercur\Catalog\Model\Task\ProductPush;
use Mercur\Core\Model\Config;
use Mercur\Process\Model\Runner;

/**
 * Queues one delta push for any number of saves; the delta picks up every
 * product updated since the last run. Products imported from Mercur are skipped.
 */
class PushProductOnSave implements ObserverInterface
{
    public function __construct(
        private readonly Runner $runner,
        private readonly Config $config,
        private readonly CatalogConfig $catalogConfig
    ) {
    }

    public function execute(Observer $observer): void
    {
        $product = $observer->getEvent()->getProduct();
        if (!$product || (int) $product->getData('mercur_product') === 1) {
            return;
        }
        if ($this->config->isEnabled() && $this->catalogConfig->exportProducts()) {
            $this->runner->enqueueOnce(ProductPush::CODE);
        }
    }
}
