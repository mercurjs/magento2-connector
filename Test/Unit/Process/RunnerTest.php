<?php

declare(strict_types=1);

namespace Mercur\Test\Unit\Process;

use Magento\Framework\Lock\LockManagerInterface;
use Mercur\Process\Model\Backoff;
use Mercur\Process\Model\Process;
use Mercur\Process\Model\ProcessConfig;
use Mercur\Process\Model\ProcessFactory;
use Mercur\Process\Model\ProcessHash;
use Mercur\Process\Model\ResourceModel\Process as ProcessResource;
use Mercur\Process\Model\ResourceModel\Process\CollectionFactory;
use Mercur\Process\Model\Runner;
use Mercur\Process\Model\TaskPool;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class RunnerTest extends TestCase
{
    private ProcessResource&MockObject $resource;

    private ProcessFactory&MockObject $factory;

    private LockManagerInterface&MockObject $lock;

    private Runner $runner;

    protected function setUp(): void
    {
        $this->resource = $this->createMock(ProcessResource::class);
        $this->factory = $this->createMock(ProcessFactory::class);
        $this->lock = $this->createMock(LockManagerInterface::class);
        $config = $this->createMock(ProcessConfig::class);
        $config->method('getMaxAttempts')->willReturn(3);
        $config->method('getTimeoutSec')->willReturn(3600);

        $this->runner = new Runner(
            new TaskPool([]),
            $this->factory,
            $this->resource,
            $this->createMock(CollectionFactory::class),
            new NullLogger(),
            $this->lock,
            $config,
            new Backoff()
        );
    }

    public function testEnqueueOnceReturnsTheExistingProcessWithoutSavingANewOne(): void
    {
        $hash = ProcessHash::compute('offers', ['full' => true]);
        $existing = $this->createMock(Process::class);
        $existing->method('getId')->willReturn(42);

        $this->resource->expects(self::once())->method('findDuplicate')->with($hash)->willReturn(42);
        $this->resource->expects(self::once())->method('load')->with($existing, 42);
        $this->resource->expects(self::never())->method('save');
        $this->factory->method('create')->willReturn($existing);

        self::assertSame($existing, $this->runner->enqueueOnce('offers', ['full' => true]));
    }

    public function testRunRefusesAProcessAnotherRunnerClaimed(): void
    {
        $process = $this->createMock(Process::class);
        $process->method('getId')->willReturn(7);
        $this->resource->method('claim')->with(7, self::anything())->willReturn(false);
        $this->resource->expects(self::never())->method('save');

        self::assertFalse($this->runner->run($process));
    }

    public function testRunPendingBailsOutWhenAnotherRunnerHoldsTheLock(): void
    {
        $this->lock->expects(self::once())->method('lock')->with(Runner::LOCK_NAME, 0)->willReturn(false);
        $this->lock->expects(self::never())->method('unlock');
        $this->resource->expects(self::never())->method('findStuck');

        self::assertSame(0, $this->runner->runPending());
    }

    public function testCancelOnlyTouchesUnfinishedProcesses(): void
    {
        $finished = $this->createMock(Process::class);
        $finished->method('getId')->willReturn(1);
        $finished->method('isFinished')->willReturn(true);
        $this->resource->expects(self::never())->method('setStatus');

        self::assertFalse($this->runner->cancel($finished));
    }
}
