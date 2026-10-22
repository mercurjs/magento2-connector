<?php

declare(strict_types=1);

namespace Mercur\Test\Unit\Connector;

use Mercur\Connector\Model\Checkout\ShadowCart;
use PHPUnit\Framework\TestCase;

final class ShadowCartLineTest extends TestCase
{
    private const CART = ['items' => [
        ['id' => 'li_1', 'offer_id' => 'off_a'],
        ['id' => 'li_2', 'offer_id' => 'off_a'],
        ['id' => 'li_3', 'offer_id' => 'off_b'],
    ]];

    public function testSkipsLinesAnotherItemAlreadyClaimed(): void
    {
        self::assertSame('li_1', ShadowCart::findLine(self::CART, 'off_a')['id'] ?? null);
        self::assertSame('li_2', ShadowCart::findLine(self::CART, 'off_a', '', ['li_1' => true])['id'] ?? null);
        self::assertNull(ShadowCart::findLine(self::CART, 'off_a', '', ['li_1' => true, 'li_2' => true]));
    }

    public function testPrefersTheLineTheItemAlreadyPointsAt(): void
    {
        self::assertSame('li_2', ShadowCart::findLine(self::CART, 'off_a', 'li_2')['id'] ?? null);
        self::assertSame('li_1', ShadowCart::findLine(self::CART, 'off_a', 'li_3')['id'] ?? null);
        self::assertSame('li_1', ShadowCart::findLine(self::CART, 'off_a', 'li_2', ['li_2' => true])['id'] ?? null);
    }

    public function testReadsTheOfferFromMetadataWhenMissingOnTheLine(): void
    {
        $cart = ['items' => [['id' => 'li_9', 'metadata' => ['offer_id' => 'off_z']]]];

        self::assertSame('li_9', ShadowCart::findLine($cart, 'off_z')['id'] ?? null);
        self::assertNull(ShadowCart::findLine($cart, 'off_y'));
    }
}
