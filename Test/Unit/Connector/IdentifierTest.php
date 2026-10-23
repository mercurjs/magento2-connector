<?php

declare(strict_types=1);

namespace Mercur\Test\Unit\Connector;

use Mercur\Connector\Model\Catalog\Identifier;
use PHPUnit\Framework\TestCase;

final class IdentifierTest extends TestCase
{
    public function testNumericCodesLoseLeadingZerosAndWhitespace(): void
    {
        self::assertSame('123456789012', Identifier::canonical(' 0123456789012 '));
        self::assertSame(Identifier::canonical('012345678905'), Identifier::canonical('0012345678905'));
    }

    public function testOtherCodesAreTrimmedAndUpperCased(): void
    {
        self::assertSame('ABC-123', Identifier::canonical(' abc-123 '));
    }

    public function testEmptyValuesHaveNoIdentifier(): void
    {
        self::assertNull(Identifier::canonical(null));
        self::assertNull(Identifier::canonical('   '));
        self::assertNull(Identifier::canonical('0000'));
    }

    public function testFormsCoverTheZeroPaddedSpellings(): void
    {
        self::assertSame(
            ['12345678905', '012345678905', '0012345678905', '00012345678905'],
            Identifier::forms('12345678905')
        );
        self::assertSame(['ABC-123'], Identifier::forms('ABC-123'));
    }

    public function testOfferIdentifiersComeFromTheOfferFirstThenTheVariant(): void
    {
        $offer = [
            'ean' => '4006381333931',
            'upc' => null,
            'product_variant' => ['ean' => '4006381333931', 'upc' => '012345678905', 'barcode' => 'abc'],
        ];

        self::assertSame(['4006381333931', '12345678905', 'ABC'], Identifier::fromOffer($offer));
    }

    public function testVariantIdentifiersIgnoreMissingKeys(): void
    {
        self::assertSame([], Identifier::fromVariant(['sku' => 'X']));
        self::assertSame(['4006381333931'], Identifier::fromVariant(['barcode' => '4006381333931']));
    }
}
