<?php

declare(strict_types=1);

namespace Mercur\Test\Unit\Process;

use Mercur\Process\Model\Backoff;
use PHPUnit\Framework\TestCase;

final class BackoffTest extends TestCase
{
    public function testDoublesPerAttemptAndCaps(): void
    {
        $backoff = new Backoff();

        self::assertSame(60, $backoff->delaySeconds(1));
        self::assertSame(120, $backoff->delaySeconds(2));
        self::assertSame(240, $backoff->delaySeconds(3));
        self::assertSame(3600, $backoff->delaySeconds(10));
        self::assertSame(3600, $backoff->delaySeconds(100));
        self::assertSame(60, $backoff->delaySeconds(0));
    }

    public function testNextRunAtHonoursAnExplicitDelay(): void
    {
        $now = new \DateTimeImmutable('2026-01-01 00:00:00', new \DateTimeZone('UTC'));

        self::assertSame('2026-01-01 00:00:10', (new Backoff())->nextRunAt(5, 10, $now));
        self::assertSame('2026-01-01 00:02:00', (new Backoff())->nextRunAt(2, null, $now));
    }
}
