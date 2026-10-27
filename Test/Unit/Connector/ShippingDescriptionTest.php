<?php

declare(strict_types=1);

namespace Mercur\Test\Unit\Connector;

use Mercur\Connector\Model\Order\ShippingDescription;
use PHPUnit\Framework\TestCase;

final class ShippingDescriptionTest extends TestCase
{
    private const NAMES = ['sel_a' => 'Sole Society', 'sel_b' => 'Kickz Corner'];

    public function testNamesEachSellerMethodWhenOnlySellersShip(): void
    {
        self::assertSame(
            'Sole Society: Standard Shipping, Kickz Corner: Express Shipping',
            ShippingDescription::compose(
                ['sel_a' => ['option_id' => 'so_1', 'name' => 'Standard Shipping'], 'sel_b' => ['name' => 'Express Shipping']],
                self::NAMES,
                null
            )
        );
    }

    public function testKeepsTheOperatorMethodFirstOnMixedCarts(): void
    {
        self::assertSame(
            'Flat Rate - Fixed, Sole Society: Standard Shipping',
            ShippingDescription::compose(['sel_a' => ['name' => 'Standard Shipping']], self::NAMES, 'Flat Rate - Fixed')
        );
    }

    public function testFallsBackToTheSellerWhenTheMethodNameIsUnknown(): void
    {
        self::assertSame('Sole Society, sel_x', ShippingDescription::compose(
            ['sel_a' => ['name' => ''], 'sel_x' => 'so_legacy'],
            self::NAMES,
            ''
        ));
    }

    public function testNothingChosenLeavesTheDescriptionAlone(): void
    {
        self::assertNull(ShippingDescription::compose([], self::NAMES, null));
    }
}
