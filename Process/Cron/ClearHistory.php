<?php

declare(strict_types=1);

namespace Mercur\Process\Cron;

use Mercur\Process\Model\HistoryCleaner;

class ClearHistory
{
    public function __construct(private readonly HistoryCleaner $cleaner)
    {
    }

    public function execute(): void
    {
        $this->cleaner->clean();
    }
}
