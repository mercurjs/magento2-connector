<?php

declare(strict_types=1);

namespace Mercur\Process\Model;

use Mercur\Process\Api\HistoryPrunerInterface;
use Mercur\Process\Model\ResourceModel\Process as ProcessResource;

class HistoryCleaner
{
    /**
     * @param HistoryPrunerInterface[] $pruners
     */
    public function __construct(
        private readonly ProcessResource $resource,
        private readonly ProcessConfig $config,
        private readonly array $pruners = []
    ) {
    }

    /**
     * @return array{processes:int, other:int}
     */
    public function clean(?int $days = null): array
    {
        $days = max(1, $days ?? $this->config->getHistoryDays());
        $cutoff = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->modify("-$days days")->format('Y-m-d H:i:s');

        $other = 0;
        foreach ($this->pruners as $pruner) {
            $other += $pruner->prune();
        }

        return ['processes' => $this->resource->deleteFinishedBefore($cutoff), 'other' => $other];
    }
}
