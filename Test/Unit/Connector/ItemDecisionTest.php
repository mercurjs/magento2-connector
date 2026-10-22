<?php

declare(strict_types=1);

namespace Mercur\Test\Unit\Connector;

use Mercur\Connector\Model\Checkout\ItemDecision;
use PHPUnit\Framework\TestCase;

final class ItemDecisionTest extends TestCase
{
    public function testWithdrawnOfferIsRemovedOrBlocksDependingOnConfig(): void
    {
        self::assertSame(ItemDecision::REMOVE, ItemDecision::decide(false, false, 0.0, 10.0, true));
        self::assertSame(ItemDecision::UNAVAILABLE, ItemDecision::decide(false, false, 0.0, 10.0, false));
    }

    public function testShortStockAlwaysBlocks(): void
    {
        self::assertSame(ItemDecision::QUANTITY, ItemDecision::decide(true, false, 10.0, 10.0, true));
        self::assertSame(ItemDecision::QUANTITY, ItemDecision::decide(true, false, 10.0, 10.0, false));
    }

    public function testPriceMovesAreRepricedAndEqualPricesKept(): void
    {
        self::assertSame(ItemDecision::REPRICE, ItemDecision::decide(true, true, 9.5, 10.0, true));
        self::assertSame(ItemDecision::KEEP, ItemDecision::decide(true, true, 10.0, 10.0, true));
        self::assertSame(ItemDecision::KEEP, ItemDecision::decide(true, true, 10.0, 10.000001, true));
    }
}
