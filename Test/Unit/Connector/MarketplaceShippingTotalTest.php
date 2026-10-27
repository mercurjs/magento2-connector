<?php

declare(strict_types=1);

namespace Mercur\Test\Unit\Connector;

use Magento\Framework\Serialize\Serializer\Json;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address\Total;
use Mercur\Connector\Model\Checkout\ShadowCart;
use Mercur\Connector\Model\Total\MarketplaceShipping;
use Mercur\Core\Model\Config;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class MarketplaceShippingTotalTest extends TestCase
{
    private function collector(): MarketplaceShipping
    {
        return new MarketplaceShipping(
            $this->createMock(ShadowCart::class),
            $this->createMock(Config::class),
            new NullLogger()
        );
    }

    public function testReadsTheAmountFromAddressDataLikeTotalsReaderPassesIt(): void
    {
        $total = new Total(['mercur_shipping_amount' => '10.0000'], new Json());

        $segment = $this->collector()->fetch($this->createMock(Quote::class), $total);

        self::assertSame('mercur_shipping', $segment['code']);
        self::assertSame(10.0, $segment['value']);
    }

    public function testReadsTheCollectedAmount(): void
    {
        $total = new Total([], new Json());
        $total->setTotalAmount('mercur_shipping', 12.5);

        self::assertSame(12.5, $this->collector()->fetch($this->createMock(Quote::class), $total)['value']);
    }

    public function testNoSegmentWithoutSellerShipping(): void
    {
        self::assertSame([], $this->collector()->fetch($this->createMock(Quote::class), new Total([], new Json())));
    }
}
