<?php

declare(strict_types=1);

namespace Mercur\Test\Unit\Connector;

use Mercur\Connector\Model\Offer;
use PHPUnit\Framework\TestCase;

final class OfferTierPriceTest extends TestCase
{
    private function offer(float $price, array $ranges): Offer
    {
        $offer = $this->getMockBuilder(Offer::class)->disableOriginalConstructor()->onlyMethods([])->getMock();
        $offer->setData(['price' => $price, 'price_ranges' => json_encode($ranges)]);

        return $offer;
    }

    public function testTierAppliesInsideItsRange(): void
    {
        $offer = $this->offer(25, [
            ['min_quantity' => 10, 'max_quantity' => 49, 'amount' => 20],
            ['min_quantity' => 50, 'max_quantity' => null, 'amount' => 18],
        ]);

        self::assertSame(25.0, $offer->getPriceForQuantity(9));
        self::assertSame(20.0, $offer->getPriceForQuantity(10));
        self::assertSame(18.0, $offer->getPriceForQuantity(80));
    }

    public function testAPriceListPriceBelowTheTierWins(): void
    {
        self::assertSame(15.0, $this->offer(15, [['min_quantity' => 10, 'max_quantity' => null, 'amount' => 20]])->getPriceForQuantity(12));
    }
}
