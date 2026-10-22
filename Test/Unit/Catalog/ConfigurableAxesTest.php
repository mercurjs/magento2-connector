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
            ['handle' => 'color', 'is_variant_axis' => true],
            ['handle' => 'size', 'is_variant_axis' => true],
            ['handle' => 'material', 'is_variant_axis' => false],
        ]));

        self::assertSame(['color', 'size'], $axes);
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
}
