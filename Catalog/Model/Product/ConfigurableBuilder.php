<?php

declare(strict_types=1);

namespace Mercur\Catalog\Model\Product;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\Data\ProductInterfaceFactory;
use Magento\Catalog\Api\ProductAttributeRepositoryInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\ConfigurableProduct\Helper\Product\Options\Factory as OptionsFactory;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Catalog\Api\Data\EavAttributeInterface;
use Magento\Catalog\Api\Data\ProductAttributeInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Mercur\Catalog\Model\CatalogConfig;
use Mercur\Catalog\Model\Product\Image\GalleryPlan;
use Mercur\Catalog\Model\Product\Image\GalleryWriter;
use Mercur\Catalog\Model\Taxonomy\AttributePuller;

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
        private readonly CatalogConfig $config,
        private readonly AttributePuller $puller,
        private readonly GalleryWriter $gallery
    ) {
    }

    /**
     * The attribute codes that can configure the parent. An axis without a
     * Magento attribute gets one created; an attribute Magento cannot
     * configure on leaves the variants as standalone products.
     *
     * @param array<int, array> $axes axis attribute entries of the Mercur product
     * @return string[] empty when the product cannot become a configurable
     */
    public function usableAxes(array $axes, ImportResult $result): array
    {
        $codes = [];
        foreach ($axes as $axis) {
            $handle = (string) $axis['handle'];
            try {
                $attribute = $this->attributes->get($handle);
            } catch (NoSuchEntityException) {
                try {
                    $attribute = $this->puller->pull($axis);
                } catch (LocalizedException $e) {
                    $result->warn(sprintf(
                        'cannot create Magento attribute "%s" for the variant axis: %s; variants imported as standalone products',
                        $handle,
                        $e->getMessage()
                    ));

                    return [];
                }
                $result->note(sprintf(
                    'Created attribute "%s" (%s) in attribute group "%s" for the Mercur variant axis',
                    $handle,
                    $attribute->getDefaultFrontendLabel(),
                    $this->config->getAttributeGroup()
                ));
            }
            if ($attribute->getFrontendInput() !== 'select' || !$this->configurableType->canUseAttribute($attribute)) {
                $result->warn(sprintf(
                    'attribute "%s" cannot configure a product (%s); variants imported as standalone products',
                    $handle,
                    $this->unusableReason($attribute)
                ));

                return [];
            }
            $codes[] = $attribute->getAttributeCode();
        }

        return $codes;
    }

    private function unusableReason(ProductAttributeInterface $attribute): string
    {
        return match (true) {
            $attribute->getFrontendInput() !== 'select' => sprintf('input "%s" is not a dropdown', $attribute->getFrontendInput()),
            $attribute->getScope() !== EavAttributeInterface::SCOPE_GLOBAL_TEXT => 'its scope is not global',
            !$attribute->getIsVisible() => 'it is not visible',
            !$attribute->getIsUserDefined() => 'it is a system attribute',
            default => 'it has no option source',
        };
    }

    /**
     * @param ProductInterface[] $children saved simple products, one per variant
     * @param string[] $axisCodes
     * @param int[] $websiteIds
     */
    public function build(array $mercurProduct, array $children, array $axisCodes, array $websiteIds, ImportResult $result): ?string
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
                    $result->warn(sprintf('variant %s has no value for "%s"; no configurable parent built', $child->getSku(), $code));

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
            ->setStoreId(0)
            ->setSku($sku)
            ->setTypeId(Configurable::TYPE_CODE)
            ->setAttributeSetId($this->config->getAttributeSetId())
            ->setStatus($this->config->getImportedProductStatus())
            ->setVisibility($this->config->getImportedProductVisibility());

        // The parent's own flag gates the configurable; availability comes from its children.
        $parent->setStockData(['use_config_manage_stock' => 1, 'is_in_stock' => 1]);
        $parent->setName((string) ($mercurProduct['title'] ?? $handle));
        $parent->setWebsiteIds($websiteIds);
        $parent->setCustomAttribute('description', (string) ($mercurProduct['description'] ?? ''));
        $parent->setCustomAttribute('mercur_product', 1);
        $parent->setCustomAttribute('mercur_product_id', (string) $mercurProduct['id']);

        $extension = $parent->getExtensionAttributes();
        $extension->setConfigurableProductOptions($this->optionsFactory->create($optionsData));
        $extension->setConfigurableProductLinks($links);
        $parent->setExtensionAttributes($extension);

        if ($this->config->importImages()) {
            $this->gallery->apply($parent, GalleryPlan::urlsFor($mercurProduct, null, false), (string) $parent->getName(), $result);
        }
        $this->products->save($parent);

        return $sku;
    }

    private function find(string $sku): ?ProductInterface
    {
        try {
            return $this->products->get($sku, true, 0);
        } catch (NoSuchEntityException) {
            return null;
        }
    }
}
