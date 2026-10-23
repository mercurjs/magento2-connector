<?php

declare(strict_types=1);

namespace Mercur\Test\Unit\Catalog;

use Mercur\Catalog\Model\Product\ConfigurableAxes;
use PHPUnit\Framework\TestCase;

final class ConfigurableAxesTest extends TestCase
{
    private function product(array $attributes, int $variants = 2): array
    {
        return [
            'id' => 'prod_1',
            'variants' => array_map(static fn (int $i) => ['id' => "var_$i", 'sku' => "SKU-$i"], range(1, $variants)),
            'attributes' => $attributes,
        ];
    }

    public function testGlobalAxesMakeAConfigurable(): void
    {
        $axes = (new ConfigurableAxes())->fromProduct($this->product([
            ['handle' => 'color', 'name' => 'Color', 'is_variant_axis' => true],
            ['handle' => 'size', 'name' => 'Size', 'is_variant_axis' => true],
            ['handle' => 'material', 'is_variant_axis' => false],
        ]));

        self::assertSame(['color', 'size'], array_column($axes, 'handle'));
        self::assertSame(['Color', 'Size'], array_column($axes, 'name'));
    }

    public function testASingleVariantStaysSimple(): void
    {
        self::assertNull((new ConfigurableAxes())->fromProduct($this->product([['handle' => 'color', 'is_variant_axis' => true]], 1)));
    }

    public function testAnInlineAxisKeepsSimpleProducts(): void
    {
        self::assertNull((new ConfigurableAxes())->fromProduct($this->product([
            ['handle' => 'color', 'is_variant_axis' => true],
            ['handle' => 'engraving', 'is_variant_axis' => true, 'is_scoped' => true],
        ])));
    }

    public function testNoAxisKeepsSimpleProducts(): void
    {
        self::assertNull((new ConfigurableAxes())->fromProduct($this->product([['handle' => 'material']])));
    }

    public function testAScopedAxisIsReportedAsInline(): void
    {
        $axes = new ConfigurableAxes();

        self::assertTrue($axes->hasInlineAxis($this->product([['handle' => 'engraving', 'is_variant_axis' => true, 'is_scoped' => true]])));
        self::assertTrue($axes->hasInlineAxis($this->product([['name' => 'Engraving', 'is_variant_axis' => true]])));
        self::assertFalse($axes->hasInlineAxis($this->product([['handle' => 'material']])));
        self::assertFalse($axes->hasInlineAxis($this->product([['handle' => 'engraving', 'is_variant_axis' => true, 'is_scoped' => true]], 1)));
    }
}
