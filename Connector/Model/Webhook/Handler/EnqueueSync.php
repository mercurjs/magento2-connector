<?php

declare(strict_types=1);

namespace Mercur\Connector\Model\Webhook\Handler;

use Mercur\Connector\Model\Webhook\HandlerInterface;
use Mercur\Process\Model\Runner;

/**
 * Catalog events only enqueue the matching delta task; the next cron tick runs it.
 * The payload is deliberately dropped so a burst of webhooks collapses into one
 * pending run.
 */
class EnqueueSync implements HandlerInterface
{
    public function __construct(
        private readonly Runner $runner,
        private readonly string $task
    ) {
    }

    public function handle(string $event, array $data): void
    {
        $this->runner->enqueueOnce($this->task);
    }
}
