<?php

declare(strict_types=1);

namespace Mercur\Test\Unit\OfferIndexer;

use Mercur\Core\Model\Config\Source\BestOfferRule;
use Mercur\OfferIndexer\Model\BestOfferSelector;
use Mercur\OfferIndexer\Model\Candidate;
use PHPUnit\Framework\TestCase;

final class BestOfferSelectorTest extends TestCase
{
    private function candidates(): array
    {
        return [
            new Candidate('offer_cheap', 'sel_1', 10.0, false, true),
            new Candidate('offer_premium', 'sel_2', 12.0, true, true),
            new Candidate('offer_unavailable', 'sel_3', 1.0, false, false),
            Candidate::operator(11.0, true),
        ];
    }

    public function testLowestPriceWinsByDefault(): void
    {
        $best = (new BestOfferSelector())->select($this->candidates(), BestOfferRule::LOWEST_TOTAL_PRICE);

        self::assertSame('offer_cheap', $best?->offerId);
    }

    public function testOperatorFirstPrefersOwnStockWhenAvailable(): void
    {
        $best = (new BestOfferSelector())->select($this->candidates(), BestOfferRule::OPERATOR_FIRST);

        self::assertNotNull($best);
        self::assertTrue($best->isOperator());
    }

    public function testPremiumFirstPrefersPremiumSellers(): void
    {
        $best = (new BestOfferSelector())->select($this->candidates(), BestOfferRule::PREMIUM_FIRST);

        self::assertSame('offer_premium', $best?->offerId);
    }

    public function testUnavailableCandidatesNeverWin(): void
    {
        $best = (new BestOfferSelector())->select([new Candidate('x', 's', 1.0, false, false)], BestOfferRule::LOWEST_TOTAL_PRICE);

        self::assertNull($best);
    }
}
