<?php

declare(strict_types=1);

namespace Mercur\Connector\Cron;

use Mercur\Connector\Model\Task\FullSync;
use Mercur\Connector\Model\Task\OfferSync;
use Mercur\Connector\Model\Task\SellerSync;
use Mercur\Core\Model\Config;
use Mercur\Process\Model\Runner;

class Enqueue
{
    public function __construct(
        private readonly Runner $runner,
        private readonly Config $config
    ) {
    }

    public function sellers(): void
    {
        $this->enqueue(SellerSync::CODE);
    }

    public function offers(): void
    {
        $this->enqueue(OfferSync::CODE);
    }

    public function products(): void
    {
        $this->enqueue('products');
    }

    public function refunds(): void
    {
        $this->enqueue('refunds');
    }

    public function orderRetry(): void
    {
        $this->enqueue('order_retry');
    }

    public function full(): void
    {
        $this->enqueue(FullSync::CODE);
    }

    private function enqueue(string $code): void
    {
        if ($this->config->isEnabledAnywhere()) {
            $this->runner->enqueueOnce($code);
        }
    }
}
