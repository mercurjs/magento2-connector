<?php

declare(strict_types=1);

namespace Mercur\Test\Unit\Connector;

use Mercur\Connector\Model\Sync\Pager;
use PHPUnit\Framework\TestCase;

final class PagerTest extends TestCase
{
    public function testWalksEveryPageAndReturnsTheHighestUpdatedAt(): void
    {
        $pages = [
            0 => ['offers' => [['id' => 'a', 'updated_at' => '2026-01-01T00:00:00Z'], ['id' => 'b', 'updated_at' => '2026-01-03T00:00:00Z']], 'count' => 3],
            2 => ['offers' => [['id' => 'c', 'updated_at' => '2026-01-02T00:00:00Z']], 'count' => 3],
        ];
        $seen = [];

        $latest = (new Pager())->walk(
            static fn (int $offset) => $pages[$offset],
            'offers',
            static function (array $rows) use (&$seen): void {
                foreach ($rows as $row) {
                    $seen[] = $row['id'];
                }
            }
        );

        self::assertSame(['a', 'b', 'c'], $seen);
        self::assertSame('2026-01-03T00:00:00Z', $latest);
    }

    public function testEmptyListReturnsNoCursor(): void
    {
        self::assertNull((new Pager())->walk(static fn () => ['offers' => [], 'count' => 0], 'offers', static fn () => null));
    }
}
