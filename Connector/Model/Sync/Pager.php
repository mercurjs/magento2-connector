<?php

declare(strict_types=1);

namespace Mercur\Connector\Model\Sync;

/**
 * Walks a paginated Mercur list ordered by updated_at and reports the highest
 * updated_at seen, which becomes the next cursor.
 */
class Pager
{
    /**
     * @param callable(int $offset): array $fetch returns the raw list response
     * @param callable(array $rows): void $handle
     */
    public function walk(callable $fetch, string $key, callable $handle): ?string
    {
        $offset = 0;
        $latest = null;

        do {
            $page = $fetch($offset);
            $rows = $page[$key] ?? [];
            if ($rows === []) {
                break;
            }
            $handle($rows);
            foreach ($rows as $row) {
                $updated = $row['updated_at'] ?? null;
                if ($updated && ($latest === null || $updated > $latest)) {
                    $latest = $updated;
                }
            }
            $offset += count($rows);
            $total = (int) ($page['count'] ?? 0);
        } while ($offset < $total);

        return $latest;
    }
}
