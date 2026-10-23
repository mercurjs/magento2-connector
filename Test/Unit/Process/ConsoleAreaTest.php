<?php

declare(strict_types=1);

namespace Mercur\Test\Unit\Process;

use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use Mercur\Process\Console\Command\RunCommand;
use Mercur\Process\Console\Command\RunProcessCommand;
use Mercur\Process\Model\ProcessFactory;
use Mercur\Process\Model\ResourceModel\Process as ProcessResource;
use Mercur\Process\Model\Runner;
use Mercur\Process\Model\TaskPool;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class ConsoleAreaTest extends TestCase
{
    public function testSyncSetsCrontabAreaWhenNoneIsSet(): void
    {
        $state = $this->stateWithoutArea();
        $state->expects(self::once())->method('setAreaCode')->with(Area::AREA_CRONTAB);

        (new CommandTester($this->runCommand($state)))->execute([]);
    }

    public function testSyncKeepsAnAreaThatIsAlreadySet(): void
    {
        $state = $this->createMock(State::class);
        $state->method('getAreaCode')->willReturn(Area::AREA_ADMINHTML);
        $state->expects(self::never())->method('setAreaCode');

        (new CommandTester($this->runCommand($state)))->execute([]);
    }

    public function testProcessRunSetsCrontabAreaWhenNoneIsSet(): void
    {
        $state = $this->stateWithoutArea();
        $state->expects(self::once())->method('setAreaCode')->with(Area::AREA_CRONTAB);

        $command = new RunProcessCommand(
            $this->createMock(Runner::class),
            $this->createMock(ProcessFactory::class),
            $this->createMock(ProcessResource::class),
            $state
        );

        try {
            (new CommandTester($command))->execute(['id' => '1']);
        } catch (\LogicException) {
            // The generated factory stub throws once the area is set; only the area matters here.
        }
    }

    private function runCommand(State $state): RunCommand
    {
        return new RunCommand($this->createMock(Runner::class), new TaskPool([]), $state);
    }

    private function stateWithoutArea(): State&MockObject
    {
        $state = $this->createMock(State::class);
        $state->method('getAreaCode')->willThrowException(new LocalizedException(new Phrase('Area code is not set')));

        return $state;
    }
}
