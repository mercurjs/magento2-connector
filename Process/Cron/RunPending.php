<?php

declare(strict_types=1);

namespace Mercur\Process\Cron;

use Mercur\Process\Model\Runner;

class RunPending
{
    public function __construct(private readonly Runner $runner)
    {
    }

    public function execute(): void
    {
        $this->runner->runPending();
    }
}
