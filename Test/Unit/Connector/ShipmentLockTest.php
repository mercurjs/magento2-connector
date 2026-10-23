<?php

declare(strict_types=1);

namespace Mercur\Test\Unit\Connector;

use Mercur\Connector\Model\Order\ShipmentLock;
use PHPUnit\Framework\TestCase;

final class ShipmentLockTest extends TestCase
{
    public function testAppliesToSellerLinesWhileEnabled(): void
    {
        $lock = new ShipmentLock();

        self::assertTrue($lock->applies(true, true));
        self::assertFalse($lock->applies(false, true));
        self::assertFalse($lock->applies(true, false));
    }

    public function testIsLiftedOnlyWhileTheCallableRuns(): void
    {
        $lock = new ShipmentLock();

        $result = $lock->lift(static function () use ($lock): string {
            self::assertFalse($lock->applies(true, true));

            return 'shipped';
        });

        self::assertSame('shipped', $result);
        self::assertTrue($lock->applies(true, true));
    }

    public function testRestoresTheLockWhenTheCallableThrows(): void
    {
        $lock = new ShipmentLock();

        try {
            $lock->lift(static fn () => throw new \RuntimeException('ship failed'));
            self::fail('expected the exception to propagate');
        } catch (\RuntimeException) {
        }

        self::assertTrue($lock->applies(true, true));
    }

    public function testNestedLiftsKeepTheOuterState(): void
    {
        $lock = new ShipmentLock();

        $lock->lift(static function () use ($lock): void {
            $lock->lift(static fn () => null);
            self::assertFalse($lock->applies(true, true));
        });

        self::assertTrue($lock->applies(true, true));
    }
}
