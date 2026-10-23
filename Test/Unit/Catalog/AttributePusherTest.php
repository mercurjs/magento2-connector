<?php

declare(strict_types=1);

namespace Mercur\Test\Unit\Catalog;

use Magento\Catalog\Api\ProductAttributeRepositoryInterface;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Eav\Model\Entity\Attribute\Source\Table;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\CollectionFactory as AttributeCollectionFactory;
use Mercur\Catalog\Model\CatalogConfig;
use Mercur\Catalog\Model\Taxonomy\AttributeGroup;
use Mercur\Catalog\Model\Taxonomy\AttributePusher;
use Mercur\Catalog\Model\Taxonomy\AttributeTypeMap;
use Mercur\Core\Model\Api\AdminApi;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class AttributePusherTest extends TestCase
{
    private AdminApi&MockObject $api;

    private Configurable&MockObject $configurable;

    protected function setUp(): void
    {
        $this->api = $this->createMock(AdminApi::class);
        $this->configurable = $this->createMock(Configurable::class);
    }

    public function testAnAttributeAConfigurableCanUseIsPushedAsAnAxis(): void
    {
        $this->configurable->method('canUseAttribute')->willReturn(true);
        $this->api->method('findAttributeByHandle')->willReturn(null);
        $this->api->expects(self::once())->method('createAttribute')
            ->with(self::callback(static function (array $payload): bool {
                self::assertTrue($payload['is_variant_axis']);
                self::assertSame('multi_select', $payload['type']);
                self::assertSame('size', $payload['handle']);
                self::assertSame([['name' => '40', 'rank' => 0, 'metadata' => ['magento_option_id' => '52']]], $payload['values']);

                return true;
            }))
            ->willReturn(['id' => 'attr_1']);

        self::assertSame('attr_1', $this->pusher()->push($this->attribute('select')));
    }

    public function testAnAxisAlreadySetInMercurIsNotDowngraded(): void
    {
        $this->configurable->method('canUseAttribute')->willReturn(false);
        $this->api->method('findAttributeByHandle')->willReturn(['id' => 'attr_1', 'is_variant_axis' => true, 'values' => [['name' => '40']]]);
        $this->api->expects(self::once())->method('updateAttribute')
            ->with('attr_1', self::callback(static function (array $payload): bool {
                self::assertTrue($payload['is_variant_axis']);
                self::assertSame('multi_select', $payload['type']);

                return true;
            }));
        $this->api->expects(self::never())->method('createAttributeValue');

        self::assertSame('attr_1', $this->pusher()->push($this->attribute('select')));
    }

    public function testAPlainDropdownStaysASingleSelect(): void
    {
        $this->configurable->method('canUseAttribute')->willReturn(false);
        $this->api->method('findAttributeByHandle')->willReturn(['id' => 'attr_1', 'is_variant_axis' => false, 'values' => []]);
        $this->api->expects(self::once())->method('updateAttribute')
            ->with('attr_1', self::callback(static function (array $payload): bool {
                self::assertFalse($payload['is_variant_axis']);
                self::assertSame('single_select', $payload['type']);

                return true;
            }));
        $this->api->expects(self::once())->method('createAttributeValue')
            ->with('attr_1', ['name' => '40', 'rank' => 0, 'metadata' => ['magento_option_id' => '52']]);

        $this->pusher()->push($this->attribute('select'));
    }

    public function testABooleanAttributeIsNeverAnAxis(): void
    {
        $this->configurable->method('canUseAttribute')->willReturn(true);
        $this->api->method('findAttributeByHandle')->willReturn(null);
        $this->api->expects(self::once())->method('createAttribute')
            ->with(self::callback(static function (array $payload): bool {
                self::assertFalse($payload['is_variant_axis']);
                self::assertSame('toggle', $payload['type']);

                return true;
            }))
            ->willReturn(['id' => 'attr_2']);

        $this->pusher()->push($this->attribute('boolean'));
    }

    private function pusher(): AttributePusher
    {
        $config = $this->createMock(CatalogConfig::class);
        $config->method('getAttributeGroup')->willReturn('Marketplace');

        return new AttributePusher(
            $this->api,
            $config,
            $this->createMock(AttributeGroup::class),
            $this->createMock(AttributeCollectionFactory::class),
            $this->createMock(ProductAttributeRepositoryInterface::class),
            new AttributeTypeMap(),
            $this->configurable
        );
    }

    private function attribute(string $input): Attribute&MockObject
    {
        $source = $this->createMock(Table::class);
        $source->method('getAllOptions')->willReturn([['value' => '52', 'label' => '40']]);

        $attribute = $this->createMock(Attribute::class);
        $attribute->method('getAttributeCode')->willReturn('size');
        $attribute->method('getFrontendInput')->willReturn($input);
        $attribute->method('getSource')->willReturn($source);
        $attribute->method('getDefaultFrontendLabel')->willReturn('Size');
        $attribute->method('getAttributeId')->willReturn(7);
        $attribute->method('getIsRequired')->willReturn(false);
        $attribute->method('getIsFilterable')->willReturn(false);
        $attribute->method('getIsFilterableInSearch')->willReturn(false);
        $attribute->method('getPosition')->willReturn(0);

        return $attribute;
    }
}
