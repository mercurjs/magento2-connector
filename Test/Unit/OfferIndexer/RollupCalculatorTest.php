<?php

declare(strict_types=1);

namespace Mercur\Test\Unit\OfferIndexer;

use Mercur\Core\Model\Config\Source\BestOfferRule;
use Mercur\OfferIndexer\Model\BestOfferSelector;
use Mercur\OfferIndexer\Model\Candidate;
use Mercur\OfferIndexer\Model\RollupCalculator;
use PHPUnit\Framework\TestCase;

final class RollupCalculatorTest extends TestCase
{
    private function calculator(): RollupCalculator
    {
        return new RollupCalculator(new BestOfferSelector());
    }

    public function testSummaryCountsOnlyAvailableSellerOffers(): void
    {
        $summary = $this->calculator()->summarise([
            new Candidate('offer_a', 'sel_1', 10.0, false, true),
            new Candidate('offer_b', 'sel_2', 30.0, false, true),
            new Candidate('offer_c', 'sel_3', 1.0, false, false),
            Candidate::operator(20.0, true),
        ], BestOfferRule::LOWEST_TOTAL_PRICE);

        self::assertSame('offer_a', $summary['best_offer_id']);
        self::assertSame('sel_1', $summary['best_seller_id']);
        self::assertSame(10.0, $summary['best_price']);
        self::assertSame(10.0, $summary['min_price']);
        self::assertSame(30.0, $summary['max_price']);
        self::assertSame(2, $summary['offer_count']);
        self::assertSame(1, $summary['operator_available']);
    }

    public function testSummaryWithNothingAvailableHasNoBest(): void
    {
        $summary = $this->calculator()->summarise([
            new Candidate('offer_c', 'sel_3', 1.0, false, false),
            Candidate::operator(20.0, false),
        ], BestOfferRule::LOWEST_TOTAL_PRICE);

        self::assertNull($summary['best_offer_id']);
        self::assertNull($summary['best_price']);
        self::assertNull($summary['min_price']);
        self::assertSame(0, $summary['offer_count']);
        self::assertSame(0, $summary['operator_available']);
    }

    public function testOperatorFirstKeepsOperatorAsBestWhileCountingOffers(): void
    {
        $summary = $this->calculator()->summarise([
            new Candidate('offer_a', 'sel_1', 10.0, false, true),
            Candidate::operator(20.0, true),
        ], BestOfferRule::OPERATOR_FIRST);

        self::assertNull($summary['best_offer_id']);
        self::assertSame(20.0, $summary['best_price']);
        self::assertSame(1, $summary['offer_count']);
    }

    public function testParentTakesCheapestChildBestAndSpansChildren(): void
    {
        $parent = $this->calculator()->parent([
            ['best_offer_id' => 'offer_a', 'best_seller_id' => 'sel_1', 'best_price' => '15.0000', 'min_price' => '15.0000', 'max_price' => '40.0000', 'offer_count' => '2', 'operator_available' => '0'],
            ['best_offer_id' => null, 'best_seller_id' => null, 'best_price' => '12.0000', 'min_price' => '12.0000', 'max_price' => '18.0000', 'offer_count' => '1', 'operator_available' => '1'],
            ['best_offer_id' => null, 'best_seller_id' => null, 'best_price' => null, 'min_price' => null, 'max_price' => null, 'offer_count' => '0', 'operator_available' => '0'],
        ]);

        self::assertNull($parent['best_offer_id']);
        self::assertSame(12.0, $parent['best_price']);
        self::assertSame(12.0, $parent['min_price']);
        self::assertSame(40.0, $parent['max_price']);
        self::assertSame(3, $parent['offer_count']);
        self::assertSame(1, $parent['operator_available']);
    }

    public function testParentOfChildrenWithoutAnythingAvailableIsEmpty(): void
    {
        $parent = $this->calculator()->parent([
            ['best_offer_id' => null, 'best_seller_id' => null, 'best_price' => null, 'min_price' => null, 'max_price' => null, 'offer_count' => '0', 'operator_available' => '0'],
        ]);

        self::assertNull($parent['best_offer_id']);
        self::assertNull($parent['best_price']);
        self::assertNull($parent['min_price']);
        self::assertSame(0, $parent['offer_count']);
    }
}
