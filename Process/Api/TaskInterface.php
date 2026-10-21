<?php

declare(strict_types=1);

namespace Mercur\Process\Api;

use Mercur\Process\Model\Process;

/**
 * A unit of background work. Implementations are registered by code in di.xml
 * and resolved by the runner; they write progress through the process.
 */
interface TaskInterface
{
    public function getCode(): string;

    public function getName(): string;

    public function execute(Process $process, array $params): void;
}
