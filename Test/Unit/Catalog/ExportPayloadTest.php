<?php

declare(strict_types=1);

namespace Mercur\Test\Unit\Catalog;

use Mercur\Catalog\Model\Product\ExportPayload;
use PHPUnit\Framework\TestCase;

final class ExportPayloadTest extends TestCase
{
    private function snapshot(array $overrides = []): array
    {
        return $overrides + [
            'magento_id' => 42,
            'sku' => 'PHONE',
            'title' => 'Phone',
            'description' => 'A phone',
            'handle' => 'phone',
            'enabled' => true,
            'weight' => 0.2,
            'images' => ['http://shop/media/catalog/product/a.jpg', 'http://shop/media/catalog/product/b.jpg'],
            'category_ids' => ['pcat_1'],
            'attributes' => ['brand' => ['values' => ['Aurelo']]],
            'axes' => ['color' => 'Color'],
            'variants' => [
                ['magento_id' => 43, 'sku' => 'PHONE-BLK', 'title' => 'Phone Black', 'weight' => 0.2, 'axes' => ['color' => 'Black']],
                ['magento_id' => 44, 'sku' => 'PHONE-WHT', 'title' => 'Phone White', 'weight' => 0.2, 'axes' => ['color' => 'White']],
            ],
        ];
    }

    private function brand(): array
    {
        return ['id' => 'attr_brand', 'name' => 'Brand', 'type' => 'single_select', 'values' => [['id' => 'val_aurelo', 'name' => 'Aurelo']]];
    }

    private function colorAxis(): array
    {
        return [
            'id' => 'attr_color', 'name' => 'Colour', 'type' => 'multi_select', 'is_variant_axis' => true,
            'values' => [['id' => 'val_black', 'name' => 'Black'], ['id' => 'val_white', 'name' => 'White']],
        ];
    }

    public function testCreateReferencesGlobalAttributesById(): void
    {
        $payload = (new ExportPayload())->create($this->snapshot(), ['brand' => $this->brand(), 'color' => $this->colorAxis()]);

        self::assertSame('published', $payload['status']);
        self::assertSame('http://shop/media/catalog/product/a.jpg', $payload['thumbnail']);
        self::assertSame([['id' => 'pcat_1']], $payload['categories']);
        self::assertSame(['source' => 'magento', 'magento_id' => 42, 'magento_sku' => 'PHONE'], $payload['metadata']);
        self::assertSame([
            ['id' => 'attr_brand', 'value_ids' => ['val_aurelo']],
            ['id' => 'attr_color', 'value_ids' => ['val_black', 'val_white']],
        ], $payload['attributes']);
        self::assertSame(['Colour' => 'Black'], $payload['variants'][0]['options']);
        self::assertSame('Black', $payload['variants'][0]['title']);
        self::assertSame('PHONE-WHT', $payload['variants'][1]['sku']);
    }

    public function testAnAxisWithoutAMercurAxisAttributeGoesInline(): void
    {
        $payload = (new ExportPayload())->create($this->snapshot(), ['brand' => null, 'color' => null]);

        self::assertSame([
            ['title' => 'Color', 'type' => 'multi_select', 'values' => ['Black', 'White'], 'is_variant_axis' => true],
        ], $payload['attributes']);
        self::assertSame(['Color' => 'White'], $payload['variants'][1]['options']);
    }

    public function testAnAxisMissingAValueInMercurGoesInline(): void
    {
        $axis = $this->colorAxis();
        $axis['values'] = [['id' => 'val_black', 'name' => 'Black']];
        $payload = (new ExportPayload())->create($this->snapshot(), ['color' => $axis]);

        self::assertSame('Color', $payload['attributes'][0]['title']);
        self::assertSame(['Color' => 'Black'], $payload['variants'][0]['options']);
    }

    public function testASimpleProductHasOneVariantWithoutOptions(): void
    {
        $payload = (new ExportPayload())->create($this->snapshot([
            'axes' => [],
            'enabled' => false,
            'variants' => [['magento_id' => 42, 'sku' => 'PHONE', 'title' => 'Phone', 'weight' => null, 'axes' => []]],
        ]), []);

        self::assertSame('draft', $payload['status']);
        self::assertArrayNotHasKey('attributes', $payload);
        self::assertSame([['title' => 'Phone', 'weight' => null, 'metadata' => ['magento_id' => 42], 'sku' => 'PHONE']], $payload['variants']);
    }

    public function testScalarAttributesUseTheirType(): void
    {
        $payload = (new ExportPayload())->create($this->snapshot([
            'axes' => [],
            'attributes' => ['waterproof' => ['values' => ['1']], 'screen' => ['values' => ['6.1']], 'note' => ['values' => ['x']], 'ram' => ['values' => ['n/a']]],
        ]), [
            'waterproof' => ['id' => 'a1', 'type' => 'toggle'],
            'screen' => ['id' => 'a2', 'type' => 'unit'],
            'note' => ['id' => 'a3', 'type' => 'text'],
            'ram' => ['id' => 'a4', 'type' => 'unit'],
        ]);

        self::assertSame([
            ['id' => 'a1', 'value' => true],
            ['id' => 'a2', 'value' => 6.1],
            ['id' => 'a3', 'value' => 'x'],
        ], $payload['attributes']);
    }

    public function testUpdateOnlyAddsVariantsAndAxisValues(): void
    {
        $existing = [
            'id' => 'prod_1',
            'attributes' => [
                ['id' => 'attr_brand', 'values' => [['id' => 'val_other', 'name' => 'Other']]],
                ['id' => 'attr_color', 'is_variant_axis' => true, 'values' => [['id' => 'val_black', 'name' => 'Black']]],
            ],
            'variants' => [['id' => 'var_blk', 'sku' => 'PHONE-BLK'], ['id' => 'var_red', 'sku' => 'PHONE-RED']],
        ];
        $plan = (new ExportPayload())->update($this->snapshot(), $existing, ['brand' => $this->brand(), 'color' => $this->colorAxis()]);

        self::assertSame([
            'update' => [
                ['id' => 'attr_brand', 'value_ids' => ['val_aurelo']],
                ['id' => 'attr_color', 'value_ids' => ['val_black', 'val_white']],
            ],
        ], $plan['attributes']);
        self::assertSame(['var_blk'], array_keys($plan['variant_updates']));
        self::assertCount(1, $plan['variant_creates']);
        self::assertSame('PHONE-WHT', $plan['variant_creates'][0]['sku']);
        self::assertSame(['Colour' => 'White'], $plan['variant_creates'][0]['options']);
        self::assertArrayNotHasKey('variants', $plan['product']);
    }

    public function testUpdateAddsMissingValuesToAnInlineAxis(): void
    {
        $existing = [
            'id' => 'prod_1',
            'attributes' => [[
                'id' => 'attr_inline', 'name' => 'Color', 'is_variant_axis' => true, 'is_scoped' => true,
                'values' => [['id' => 'v1', 'name' => 'Black']], 'all_values' => [['id' => 'v1', 'name' => 'Black']],
            ]],
            'variants' => [['id' => 'var_blk', 'sku' => 'PHONE-BLK']],
        ];
        $plan = (new ExportPayload())->update($this->snapshot(['attributes' => []]), $existing, ['color' => null]);

        self::assertSame(['update' => [['id' => 'attr_inline', 'add' => ['White']]]], $plan['attributes']);
    }
}
