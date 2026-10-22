<?php

declare(strict_types=1);

namespace Mercur\Test\Unit\Connector;

use Mercur\Connector\Model\Checkout\ShippingErrorGate;
use PHPUnit\Framework\TestCase;

final class ShippingErrorGateTest extends TestCase
{
    public function testBlocksOnlyWhenSellerLinesCouldNotBeQuoted(): void
    {
        self::assertTrue(ShippingErrorGate::isBlocked('Marketplace shipping could not be quoted.', true));
        self::assertFalse(ShippingErrorGate::isBlocked('Marketplace shipping could not be quoted.', false));
        self::assertFalse(ShippingErrorGate::isBlocked(null, true));
        self::assertFalse(ShippingErrorGate::isBlocked('  ', true));
    }
}
