<?php

declare(strict_types=1);

namespace Mercur\Test\Unit\Settlement;

use Mercur\Settlement\Model\RefundAllocator;
use PHPUnit\Framework\TestCase;

final class RefundAllocatorTest extends TestCase
{
    public function testLinesAreSummedPerSellerOrder(): void
    {
        $result = (new RefundAllocator())->allocate([
            ['seller_order' => 'order_a', 'seller' => 'sel_a', 'amount' => 10.0],
            ['seller_order' => 'order_a', 'seller' => 'sel_a', 'amount' => 5.5],
            ['seller_order' => 'order_b', 'seller' => 'sel_b', 'amount' => 3.0],
        ], 0.0, []);

        self::assertSame(['order_a' => 15.5, 'order_b' => 3.0], $result);
    }

    public function testShippingIsSharedByWhatEachSellerCharged(): void
    {
        $result = (new RefundAllocator())->allocate([
            ['seller_order' => 'order_a', 'seller' => 'sel_a', 'amount' => 10.0],
            ['seller_order' => 'order_b', 'seller' => 'sel_b', 'amount' => 10.0],
        ], 8.0, ['sel_a' => 6.0, 'sel_b' => 2.0]);

        self::assertSame(['order_a' => 16.0, 'order_b' => 12.0], $result);
    }

    public function testShippingIsSplitEvenlyWhenNoSellerChargedForIt(): void
    {
        $result = (new RefundAllocator())->allocate([
            ['seller_order' => 'order_a', 'seller' => 'sel_a', 'amount' => 1.0],
            ['seller_order' => 'order_b', 'seller' => 'sel_b', 'amount' => 1.0],
        ], 3.0, []);

        self::assertSame(['order_a' => 2.5, 'order_b' => 2.5], $result);
    }

    public function testNothingRefundedProducesNothing(): void
    {
        self::assertSame([], (new RefundAllocator())->allocate([], 5.0, ['sel_a' => 5.0]));
    }
}
