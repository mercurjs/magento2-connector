<?php

declare(strict_types=1);

namespace Mercur\Test\Unit\Connector;

use Mercur\Connector\Model\Checkout\SyncHash;
use PHPUnit\Framework\TestCase;

final class SyncHashTest extends TestCase
{
    public function testOrderOfKeysDoesNotMatter(): void
    {
        $a = SyncHash::build(['off_1' => 2, 'off_2' => 1], ['city' => 'Berlin', 'country_code' => 'de'], ['sel_1' => 'so_1']);
        $b = SyncHash::build(['off_2' => 1, 'off_1' => 2], ['country_code' => 'de', 'city' => 'Berlin'], ['sel_1' => 'so_1']);

        self::assertSame($a, $b);
        self::assertSame(40, strlen($a));
    }

    public function testAnyInputChangesTheHash(): void
    {
        $base = SyncHash::build(['off_1' => 2], ['country_code' => 'de'], ['sel_1' => 'so_1']);

        self::assertNotSame($base, SyncHash::build(['off_1' => 3], ['country_code' => 'de'], ['sel_1' => 'so_1']));
        self::assertNotSame($base, SyncHash::build(['off_1' => 2], ['country_code' => 'fr'], ['sel_1' => 'so_1']));
        self::assertNotSame($base, SyncHash::build(['off_1' => 2], ['country_code' => 'de'], ['sel_1' => 'so_2']));
        self::assertNotSame($base, SyncHash::build(['off_1' => 2], ['country_code' => 'de'], []));
    }
}
