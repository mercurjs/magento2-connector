<?php

declare(strict_types=1);

namespace Mercur\Test\Unit\Catalog;

use Mercur\Catalog\Model\Product\VariantAxisValues;
use PHPUnit\Framework\TestCase;

final class VariantAxisValuesTest extends TestCase
{
    private const AXIS = ['handle' => 'size', 'name' => 'Size'];

    public function testReadsTheVariantsOwnValueByOptionTitle(): void
    {
        $variant = ['sku' => 'VR-43', 'options' => [
            ['value' => '43', 'option' => ['title' => 'Size']],
            ['value' => 'Beige', 'option' => ['title' => 'Color']],
        ]];

        self::assertSame(['43'], VariantAxisValues::forVariant(self::AXIS, $variant));
    }

    public function testMatchesTheHandleWhenTheTitleIsSpelledLikeIt(): void
    {
        $variant = ['options' => [['value' => '43', 'option' => ['title' => 'SIZE']]]];

        self::assertSame(['43'], VariantAxisValues::forVariant(['handle' => 'size', 'name' => 'Shoe size'], $variant));
    }

    public function testAcceptsTheLegacyFlatTitleKey(): void
    {
        $variant = ['options' => [['value' => '43', 'option_title' => 'Size']]];

        self::assertSame(['43'], VariantAxisValues::forVariant(self::AXIS, $variant));
    }

    public function testVariantWithoutOptionsHasNoValue(): void
    {
        self::assertSame([], VariantAxisValues::forVariant(self::AXIS, ['sku' => 'VR-43']));
        self::assertSame([], VariantAxisValues::forVariant(self::AXIS, ['options' => [['value' => 'Beige', 'option' => ['title' => 'Color']]]]));
    }
}
