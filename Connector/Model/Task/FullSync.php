<?php

declare(strict_types=1);

namespace Mercur\Connector\Model\Task;

use Mercur\Process\Api\TaskInterface;
use Mercur\Process\Model\Process;
use Mercur\Process\Model\Runner;

/**
 * Sellers before offers, offers before products: each later task reads what the
 * earlier one wrote. The steps are queued as child processes so each has its
 * own log, attempts and timeout; the runner picks them up in creation order.
 */
class FullSync implements TaskInterface
{
    public const CODE = 'full';

    /**
     * @param string[] $order task codes in execution order
     */
    public function __construct(
        private readonly Runner $runner,
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
        $parentId = (int) $process->getId();
        foreach ($this->order as $code) {
            $child = $this->runner->enqueue($code, ['full' => true] + $params, $parentId);
            $process->output(sprintf('Queued %s as process #%d', $code, $child->getId()));
        }
    }
}
