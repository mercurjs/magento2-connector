<?php

declare(strict_types=1);

namespace Mercur\Test\Unit\Connector;

use Mercur\Connector\Model\Sync\OfferPriceMapper;
use PHPUnit\Framework\TestCase;

final class OfferPriceMapperTest extends TestCase
{
    private function offer(array $calculated = []): array
    {
        return [
            'id' => 'offer_1',
            'calculated_price' => array_replace([
                'calculated_amount' => 25,
                'original_amount' => 25,
                'currency_code' => 'usd',
                'is_calculated_price_price_list' => false,
            ], $calculated),
            'prices' => [
                ['amount' => 25, 'currency_code' => 'usd', 'min_quantity' => null, 'max_quantity' => null],
                ['amount' => 20, 'currency_code' => 'usd', 'min_quantity' => 10, 'max_quantity' => null],
                ['amount' => 22, 'currency_code' => 'eur', 'min_quantity' => null, 'max_quantity' => null],
            ],
        ];
    }

    public function testMapsTheRegionPriceAndTiers(): void
    {
        $row = (new OfferPriceMapper())->map($this->offer(), 2, 'USD');

        self::assertSame('offer_1', $row['offer_id']);
        self::assertSame(2, $row['website_id']);
        self::assertSame('USD', $row['currency_code']);
        self::assertSame(25.0, $row['price']);
        self::assertNull($row['origin_price']);
        self::assertEquals([['min_quantity' => 10, 'max_quantity' => null, 'amount' => 20]], json_decode($row['price_ranges'], true));
    }

    public function testPriceListDiscountKeepsTheOriginalForStrikeThrough(): void
    {
        $row = (new OfferPriceMapper())->map($this->offer([
            'calculated_amount' => 19.5,
            'is_calculated_price_price_list' => true,
        ]), 1, 'USD');

        self::assertSame(19.5, $row['price']);
        self::assertSame(25.0, $row['origin_price']);
    }

    public function testRegionInAnotherCurrencyIsRejected(): void
    {
        self::assertNull((new OfferPriceMapper())->map($this->offer(['currency_code' => 'eur']), 1, 'USD'));
    }

    public function testFallsBackToTheRawPriceWithoutCalculatedPrice(): void
    {
        $offer = $this->offer();
        unset($offer['calculated_price']);

        self::assertSame(25.0, (new OfferPriceMapper())->map($offer, 1, 'USD')['price']);
    }
}
