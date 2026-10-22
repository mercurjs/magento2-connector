<?php

declare(strict_types=1);

namespace Mercur\Test\Unit\Process;

use Mercur\Process\Model\ProcessHash;
use PHPUnit\Framework\TestCase;

final class ProcessHashTest extends TestCase
{
    public function testKeyOrderDoesNotChangeTheHash(): void
    {
        $a = ProcessHash::compute('offers', ['full' => true, 'ids' => ['b' => 2, 'a' => 1]]);
        $b = ProcessHash::compute('offers', ['ids' => ['a' => 1, 'b' => 2], 'full' => true]);

        self::assertSame($a, $b);
        self::assertSame(40, strlen($a));
    }

    public function testCodeAndParamsBothMatter(): void
    {
        self::assertNotSame(ProcessHash::compute('offers', []), ProcessHash::compute('sellers', []));
        self::assertNotSame(ProcessHash::compute('offers', []), ProcessHash::compute('offers', ['full' => true]));
    }

    public function testListOrderIsPreserved(): void
    {
        self::assertNotSame(ProcessHash::compute('x', ['ids' => [1, 2]]), ProcessHash::compute('x', ['ids' => [2, 1]]));
    }
}
