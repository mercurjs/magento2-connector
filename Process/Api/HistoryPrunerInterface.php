<?php

declare(strict_types=1);

namespace Mercur\Process\Api;

/**
 * Something with rows that age out alongside finished processes. Registered
 * on the history cleaner in di.xml and run by the daily cron.
 */
interface HistoryPrunerInterface
{
    /**
     * @return int rows removed
     */
    public function prune(): int;
}
