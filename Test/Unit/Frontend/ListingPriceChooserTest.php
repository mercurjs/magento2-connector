<?php

declare(strict_types=1);

namespace Mercur\Test\Unit\Frontend;

use Mercur\Core\Model\Config\Source\BestOfferRule;
use Mercur\Frontend\Model\ListingPriceChooser;
use PHPUnit\Framework\TestCase;

final class ListingPriceChooserTest extends TestCase
{
    private function summary(array $overrides = []): array
    {
        return array_replace([
            'best_offer_id' => 'offer_1',
            'best_seller_id' => 'sel_1',
            'best_price' => '15.0000',
            'min_price' => '15.0000',
            'max_price' => '30.0000',
            'offer_count' => '2',
            'operator_available' => '0',
        ], $overrides);
    }

    public function testOperatorOutOfStockShowsCheapestOfferAsFrom(): void
    {
        $choice = (new ListingPriceChooser())->choose(20.0, false, $this->summary(), BestOfferRule::LOWEST_TOTAL_PRICE);

        self::assertSame(['price' => 15.0, 'from' => true], $choice);
    }

    public function testSingleOfferIsNotAFromPrice(): void
    {
        $choice = (new ListingPriceChooser())->choose(null, false, $this->summary([
            'max_price' => '15.0000',
            'offer_count' => '1',
        ]), BestOfferRule::LOWEST_TOTAL_PRICE);

        self::assertSame(['price' => 15.0, 'from' => false], $choice);
    }

    public function testLiveOwnPriceAtOrBelowBestKeepsOwnPriceBox(): void
    {
        $chooser = new ListingPriceChooser();

        self::assertNull($chooser->choose(15.0, true, $this->summary(['operator_available' => '1']), BestOfferRule::LOWEST_TOTAL_PRICE));
        self::assertNull($chooser->choose(12.0, true, $this->summary(['operator_available' => '1']), BestOfferRule::LOWEST_TOTAL_PRICE));
    }

    public function testOwnPriceAboveBestShowsOfferAndCountsOwnAsAWay(): void
    {
        $choice = (new ListingPriceChooser())->choose(40.0, true, $this->summary([
            'max_price' => '15.0000',
            'offer_count' => '1',
            'operator_available' => '1',
        ]), BestOfferRule::LOWEST_TOTAL_PRICE);

        self::assertSame(['price' => 15.0, 'from' => true], $choice);
    }

    public function testOperatorFirstKeepsOwnPriceWhileSalable(): void
    {
        $chooser = new ListingPriceChooser();

        self::assertNull($chooser->choose(40.0, true, $this->summary(['operator_available' => '1']), BestOfferRule::OPERATOR_FIRST));
        self::assertSame(
            ['price' => 15.0, 'from' => true],
            $chooser->choose(40.0, false, $this->summary(), BestOfferRule::OPERATOR_FIRST)
        );
    }

    public function testNoAvailableOfferKeepsOwnPriceBox(): void
    {
        self::assertNull((new ListingPriceChooser())->choose(null, false, $this->summary([
            'best_offer_id' => null,
            'best_price' => null,
            'min_price' => null,
            'max_price' => null,
            'offer_count' => '0',
        ]), BestOfferRule::LOWEST_TOTAL_PRICE));
    }
}
