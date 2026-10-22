<?php

declare(strict_types=1);

namespace Mercur\Catalog\Model\Product;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\Data\ProductInterfaceFactory;
use Magento\Catalog\Api\ProductAttributeRepositoryInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\ConfigurableProduct\Helper\Product\Options\Factory as OptionsFactory;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\Exception\NoSuchEntityException;
use Mercur\Catalog\Model\CatalogConfig;

/**
 * Groups the simple products of one Mercur product under a configurable
 * parent, configured on the Magento attributes behind the variant axes.
 */
class ConfigurableBuilder
{
    public const PARENT_SKU_PREFIX = 'mercur-';

    public function __construct(
        private readonly ProductRepositoryInterface $products,
        private readonly ProductInterfaceFactory $productFactory,
        private readonly ProductAttributeRepositoryInterface $attributes,
        private readonly Configurable $configurableType,
        private readonly OptionsFactory $optionsFactory,
        private readonly CatalogConfig $config
    ) {
    }

    /**
     * @param string[] $axisHandles
     * @return string[] attribute codes that can configure the parent, empty when one cannot
     */
    public function usableAxes(array $axisHandles): array
    {
        $codes = [];
        foreach ($axisHandles as $handle) {
            try {
                $attribute = $this->attributes->get($handle);
            } catch (NoSuchEntityException) {
                return [];
            }
            if (!$this->configurableType->canUseAttribute($attribute)) {
                return [];
            }
            $codes[] = $attribute->getAttributeCode();
        }

        return $codes;
    }

    /**
     * @param ProductInterface[] $children saved simple products, one per variant
     * @param string[] $axisCodes
     * @param int[] $websiteIds
     */
    public function build(array $mercurProduct, array $children, array $axisCodes, array $websiteIds): ?string
    {
        $handle = (string) ($mercurProduct['handle'] ?? $mercurProduct['id']);
        $sku = self::PARENT_SKU_PREFIX . $handle;

        $values = [];
        $links = [];
        foreach ($children as $child) {
            $links[] = (int) $child->getId();
            foreach ($axisCodes as $code) {
                $value = $child->getCustomAttribute($code)?->getValue();
                if ($value === null || $value === '') {
                    return null;
                }
                $values[$code][(string) $value] = true;
            }
        }

        $optionsData = [];
        foreach ($axisCodes as $position => $code) {
            $attribute = $this->attributes->get($code);
            $optionsData[] = [
                'attribute_id' => $attribute->getAttributeId(),
                'code' => $code,
                'label' => $attribute->getDefaultFrontendLabel(),
                'position' => $position,
                'values' => array_map(
                    static fn (string $valueIndex) => ['attribute_id' => $attribute->getAttributeId(), 'value_index' => $valueIndex],
                    array_keys($values[$code])
                ),
            ];
        }

        $parent = $this->find($sku) ?? $this->productFactory->create()
            ->setSku($sku)
            ->setTypeId(Configurable::TYPE_CODE)
            ->setAttributeSetId($this->config->getAttributeSetId())
            ->setStatus($this->config->getImportedProductStatus())
            ->setVisibility($this->config->getImportedProductVisibility())
            ->setStockData(['use_config_manage_stock' => 1, 'is_in_stock' => 1]);

        $parent->setName((string) ($mercurProduct['title'] ?? $handle));
        $parent->setWebsiteIds($websiteIds);
        $parent->setCustomAttribute('description', (string) ($mercurProduct['description'] ?? ''));
        $parent->setCustomAttribute('mercur_product', 1);
        $parent->setCustomAttribute('mercur_product_id', (string) $mercurProduct['id']);

        $extension = $parent->getExtensionAttributes();
        $extension->setConfigurableProductOptions($this->optionsFactory->create($optionsData));
        $extension->setConfigurableProductLinks($links);
        $parent->setExtensionAttributes($extension);

        $this->products->save($parent);

        return $sku;
    }

    private function find(string $sku): ?ProductInterface
    {
        try {
            return $this->products->get($sku, true);
        } catch (NoSuchEntityException) {
            return null;
        }
    }
}
