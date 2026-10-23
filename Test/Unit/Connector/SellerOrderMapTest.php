<?php

declare(strict_types=1);

namespace Mercur\Test\Unit\Connector;

use Mercur\Connector\Model\Order\SellerOrderMap;
use PHPUnit\Framework\TestCase;

final class SellerOrderMapTest extends TestCase
{
    public function testUsesTheSellerOnTheChildOrderWhenPresent(): void
    {
        $lookups = 0;
        $map = SellerOrderMap::build([
            ['id' => 'order_1', 'seller_id' => 'sel_1'],
            ['id' => 'order_2', 'seller' => ['id' => 'sel_2']],
        ], static function () use (&$lookups): ?string {
            $lookups++;

            return null;
        });

        self::assertSame(['sel_1' => 'order_1', 'sel_2' => 'order_2'], $map);
        self::assertSame(0, $lookups);
    }

    public function testLooksUpTheSellerWhenTheGroupOmitsIt(): void
    {
        $map = SellerOrderMap::build(
            [['id' => 'order_1', 'status' => 'pending'], ['id' => 'order_2']],
            static fn (string $orderId) => $orderId === 'order_1' ? 'sel_1' : null
        );

        self::assertSame(['sel_1' => 'order_1'], $map);
    }
}
