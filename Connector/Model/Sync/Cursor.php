<?php

declare(strict_types=1);

namespace Mercur\Connector\Model\Sync;

use Magento\Framework\FlagManager;

/**
 * Remembers the `updated_at` high-water mark of the last successful delta sync per task.
 */
class Cursor
{
    private const PREFIX = 'mercur_sync_cursor_';

    public function __construct(private readonly FlagManager $flags)
    {
    }

    public function get(string $task): ?string
    {
        $value = $this->flags->getFlagData(self::PREFIX . $task);

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function set(string $task, string $isoDate): void
    {
        $this->flags->saveFlag(self::PREFIX . $task, $isoDate);
    }

    public function clear(string $task): void
    {
        $this->flags->deleteFlag(self::PREFIX . $task);
    }
}
