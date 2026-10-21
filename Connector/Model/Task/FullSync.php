<?php

declare(strict_types=1);

namespace Mercur\Connector\Model\Task;

use Mercur\Process\Api\TaskInterface;
use Mercur\Process\Model\Process;
use Mercur\Process\Model\TaskPool;

/**
 * Sellers before offers, offers before products: each later task reads what the
 * earlier one wrote.
 */
class FullSync implements TaskInterface
{
    public const CODE = 'full';

    /**
     * @param string[] $order task codes in execution order
     */
    public function __construct(
        private readonly TaskPool $tasks,
        private readonly array $order = []
    ) {
    }

    public function getCode(): string
    {
        return self::CODE;
    }

    public function getName(): string
    {
        return 'Full resync';
    }

    public function execute(Process $process, array $params): void
    {
        foreach ($this->order as $code) {
            $process->output("Running $code");
            $this->tasks->get($code)->execute($process, ['full' => true] + $params);
        }
    }
}
