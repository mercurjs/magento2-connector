<?php

declare(strict_types=1);

namespace Mercur\Connector\Model\Webhook\Handler;

use Mercur\Connector\Model\Webhook\HandlerInterface;
use Mercur\Process\Model\Runner;

/**
 * Catalog events only enqueue the matching delta task; the next cron tick runs it.
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
        $this->runner->enqueue($this->task, ['event' => $event, 'id' => $data['id'] ?? null]);
    }
}
