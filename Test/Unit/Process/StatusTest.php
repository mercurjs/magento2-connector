<?php

declare(strict_types=1);

namespace Mercur\Test\Unit\Process;

use Mercur\Process\Model\Process;
use Mercur\Process\Model\Source\Status;
use PHPUnit\Framework\TestCase;

final class StatusTest extends TestCase
{
    public function testEveryStatusIsEitherActiveOrFinal(): void
    {
        $all = [
            Process::STATUS_PENDING,
            Process::STATUS_PENDING_RETRY,
            Process::STATUS_PROCESSING,
            Process::STATUS_COMPLETED,
            Process::STATUS_ERROR,
            Process::STATUS_TIMEOUT,
            Process::STATUS_STOPPED,
        ];

        self::assertEmpty(array_intersect(Process::ACTIVE_STATUSES, Process::FINAL_STATUSES));
        self::assertEqualsCanonicalizing($all, array_merge(Process::ACTIVE_STATUSES, Process::FINAL_STATUSES));
        self::assertEmpty(array_diff(Process::RUNNABLE_STATUSES, Process::ACTIVE_STATUSES));
        self::assertNotContains(Process::STATUS_PROCESSING, Process::RUNNABLE_STATUSES);
    }

    public function testSourceListsEveryStatusOnce(): void
    {
        $values = array_column((new Status())->toOptionArray(), 'value');

        self::assertSame(array_keys(Status::labels()), $values);
        self::assertEqualsCanonicalizing(array_merge(Process::ACTIVE_STATUSES, Process::FINAL_STATUSES), $values);
    }
}
