<?php

declare(strict_types=1);

namespace Mercur\Test\Unit\Connector;

use Mercur\Connector\Model\Delivery\Estimator;
use PHPUnit\Framework\TestCase;

final class EstimatorTest extends TestCase
{
    private const WEEKDAYS = [1, 2, 3, 4, 5];

    private function window(string $now, ?int $leadtime, int $min, int $max, string $cutoff = '14:00'): array
    {
        $result = (new Estimator())->estimate(new \DateTimeImmutable($now), $leadtime, $min, $max, $cutoff, self::WEEKDAYS);

        return [$result['from']->format('Y-m-d'), $result['to']->format('Y-m-d')];
    }

    public function testOrderBeforeCutoffStartsToday(): void
    {
        // Wednesday 2026-10-21, 2 days lead time, 1-3 days transit.
        self::assertSame(['2026-10-26', '2026-10-28'], $this->window('2026-10-21 10:00', 2, 1, 3));
    }

    public function testOrderAfterCutoffStartsNextBusinessDay(): void
    {
        self::assertSame(['2026-10-27', '2026-10-29'], $this->window('2026-10-21 15:00', 2, 1, 3));
    }

    public function testWeekendOrderStartsMonday(): void
    {
        // Saturday 2026-10-24 → Monday 26, no lead time, next-day transit.
        self::assertSame(['2026-10-27', '2026-10-27'], $this->window('2026-10-24 09:00', null, 1, 1));
    }

    public function testMaxBelowMinIsRaisedToMin(): void
    {
        self::assertSame(['2026-10-23', '2026-10-23'], $this->window('2026-10-21 10:00', 0, 2, 1));
    }

    public function testEmptyCutoffNeverDefersTheStart(): void
    {
        self::assertSame(['2026-10-22', '2026-10-22'], $this->window('2026-10-21 23:30', 0, 1, 1, ''));
    }
}
