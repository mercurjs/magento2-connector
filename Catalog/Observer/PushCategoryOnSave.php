<?php

declare(strict_types=1);

namespace Mercur\Catalog\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Mercur\Catalog\Model\Task\TaxonomyPush;
use Mercur\Core\Model\Config;
use Mercur\Process\Model\Runner;

class PushCategoryOnSave implements ObserverInterface
{
    public function __construct(
        private readonly Runner $runner,
        private readonly Config $config
    ) {
    }

    public function execute(Observer $observer): void
    {
        if ($this->config->isEnabled()) {
            $this->runner->enqueue(TaxonomyPush::CODE);
        }
    }
}
