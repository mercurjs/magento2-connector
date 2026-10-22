<?php

declare(strict_types=1);

namespace Mercur\Test\Unit\Connector;

use Mercur\Connector\Model\Tax\SellerShippingTax;
use PHPUnit\Framework\TestCase;

final class SellerShippingTaxTest extends TestCase
{
    public function testSplitsNetAndGrossAmounts(): void
    {
        self::assertSame(['excl' => 10.0, 'tax' => 1.9, 'incl' => 11.9], SellerShippingTax::split(10.0, 19.0, false));
        self::assertSame(['excl' => 10.0, 'tax' => 1.9, 'incl' => 11.9], SellerShippingTax::split(11.9, 19.0, true));
        self::assertSame(['excl' => 10.0, 'tax' => 0.0, 'incl' => 10.0], SellerShippingTax::split(10.0, 0.0, true));
        self::assertSame(['excl' => 0.0, 'tax' => 0.0, 'incl' => 0.0], SellerShippingTax::split(0.0, 19.0, false));
    }

    public function testProratesTheOrderTaxForPartialDocuments(): void
    {
        self::assertSame(['tax' => 1.9, 'incl' => 11.9], SellerShippingTax::prorate(10.0, 10.0, 1.9));
        self::assertSame(['tax' => 0.95, 'incl' => 5.95], SellerShippingTax::prorate(5.0, 10.0, 1.9));
        self::assertSame(['tax' => 0.0, 'incl' => 0.0], SellerShippingTax::prorate(0.0, 10.0, 1.9));
        self::assertSame(['tax' => 0.0, 'incl' => 5.0], SellerShippingTax::prorate(5.0, 0.0, 0.0));
    }
}
