<?php

declare(strict_types=1);

namespace Mercur\Test\Unit\Process;

use Magento\Framework\Phrase;
use Magento\Framework\Phrase\Renderer\Placeholder;
use Mercur\Process\Block\Adminhtml\Process\View;
use Mercur\Process\Model\Process;
use PHPUnit\Framework\TestCase;

final class ProcessViewTest extends TestCase
{
    protected function setUp(): void
    {
        Phrase::setRenderer(new Placeholder());
    }

    public function testRowsAreKeyedByRenderedLabels(): void
    {
        $rows = $this->block($this->process(['duration' => 12, 'locked_by' => 'web-1']))->getRows();

        self::assertContainsOnly('string', array_keys($rows));
        self::assertContainsOnly('string', $rows);
        self::assertSame('offers', $rows['Task']);
        self::assertSame('Completed', $rows['Status']);
        self::assertSame('1 / 3', $rows['Attempts']);
        self::assertSame('12 s', $rows['Duration']);
        self::assertSame('web-1', $rows['Runner']);
    }

    public function testMissingValuesRenderAsDashes(): void
    {
        $rows = $this->block($this->process())->getRows();

        self::assertSame('—', $rows['Duration']);
        self::assertSame('—', $rows['Runner']);
        self::assertSame('—', $rows['Next run']);
    }

    private function process(array $data = []): Process
    {
        $data += ['name' => 'Sync offers', 'created_at' => '2026-10-23 12:00:00'];
        $process = $this->createMock(Process::class);
        $process->method('getCode')->willReturn('offers');
        $process->method('getStatus')->willReturn('completed');
        $process->method('getAttempts')->willReturn(1);
        $process->method('getMaxAttempts')->willReturn(3);
        $process->method('getData')->willReturnCallback(static fn (string $key) => $data[$key] ?? null);

        return $process;
    }

    private function block(Process $process): View
    {
        $block = $this->getMockBuilder(View::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getProcess', 'formatStamp'])
            ->getMock();
        $block->method('getProcess')->willReturn($process);
        $block->method('formatStamp')->willReturnCallback(static fn (?string $value) => $value ?? '—');

        return $block;
    }
}
