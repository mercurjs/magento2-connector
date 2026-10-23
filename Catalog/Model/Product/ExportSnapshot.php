<?php

declare(strict_types=1);

namespace Mercur\Catalog\Model\Product;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductAttributeRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Media\Config as MediaConfig;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\Exception\NoSuchEntityException;
use Mercur\Catalog\Model\Taxonomy\AttributePusher;
use Mercur\Catalog\Model\Taxonomy\CategoryPusher;

/**
 * Reads a Magento product into the plain array ExportPayload maps to Mercur:
 * a configurable becomes one product with a variant per child, anything else
 * one product with a single variant.
 */
class ExportSnapshot
{
    /** @var string[]|null */
    private ?array $attributeCodes = null;

    public function __construct(
        private readonly AttributePusher $attributes,
        private readonly CategoryPusher $categories,
        private readonly CategoryCollectionFactory $categoryCollections,
        private readonly MediaConfig $mediaConfig,
        private readonly ProductAttributeRepositoryInterface $attributeRepository
    ) {
    }

    public function build(ProductInterface $product): array
    {
        /** @var Product $product */
        $axes = [];
        $variants = [];
        if ($product->getTypeId() === Configurable::TYPE_CODE) {
            /** @var Configurable $type */
            $type = $product->getTypeInstance();
            foreach ($type->getConfigurableAttributes($product) as $configurable) {
                $attribute = $configurable->getProductAttribute();
                $axes[(string) $attribute->getAttributeCode()] = (string) ($configurable->getLabel() ?: $attribute->getDefaultFrontendLabel());
            }
            foreach ($type->getUsedProducts($product) as $child) {
                $variant = $this->variant($child);
                foreach (array_keys($axes) as $code) {
                    $variant['axes'][$code] = (string) $child->getAttributeText($code);
                }
                $variants[] = $variant;
            }
        } elseif ((string) $product->getSku() !== '') {
            $variants[] = $this->variant($product);
        }

        return [
            'magento_id' => (int) $product->getId(),
            'sku' => (string) $product->getSku(),
            'title' => (string) $product->getName(),
            'description' => (string) ($product->getData('description') ?? '') ?: null,
            'handle' => (string) $product->getUrlKey() ?: null,
            'enabled' => (int) $product->getStatus() === Status::STATUS_ENABLED,
            'weight' => $product->getWeight() !== null ? (float) $product->getWeight() : null,
            'images' => $this->images($product),
            'category_ids' => $this->categoryIds($product),
            'attributes' => $this->attributeValues($product, array_keys($axes)),
            'axes' => $axes,
            'variants' => $variants,
        ];
    }

    private function variant(Product $product): array
    {
        return [
            'magento_id' => (int) $product->getId(),
            'sku' => (string) $product->getSku(),
            'title' => (string) $product->getName(),
            'weight' => $product->getWeight() !== null ? (float) $product->getWeight() : null,
            'axes' => [],
        ];
    }

    /**
     * Gallery images as absolute URLs, the base image first so it becomes the thumbnail.
     *
     * @return string[]
     */
    private function images(Product $product): array
    {
        $files = [];
        $base = (string) $product->getImage();
        if ($base !== '' && $base !== 'no_selection') {
            $files[] = $base;
        }
        foreach ($product->getMediaGalleryEntries() ?? [] as $entry) {
            if (!$entry->isDisabled() && $entry->getMediaType() === 'image') {
                $files[] = (string) $entry->getFile();
            }
        }

        return array_map(
            fn (string $file) => $this->mediaConfig->getMediaUrl($file),
            array_values(array_unique(array_filter($files)))
        );
    }

    /**
     * @return string[] Mercur ids of the product's categories that the taxonomy push has created
     */
    private function categoryIds(Product $product): array
    {
        $ids = array_map('intval', $product->getCategoryIds());
        if ($ids === []) {
            return [];
        }
        $collection = $this->categoryCollections->create()
            ->addAttributeToSelect(['name', 'url_key'])
            ->addFieldToFilter('entity_id', ['in' => $ids])
            ->addFieldToFilter('level', ['gt' => 1]);

        $mercurIds = [];
        foreach ($collection as $category) {
            $id = $this->categories->findMercurId($category);
            if ($id !== null) {
                $mercurIds[] = $id;
            }
        }

        return $mercurIds;
    }

    /**
     * Values of the marketplace attributes, as labels. The connector's own
     * `mercur_*` attributes and the configurable axes are left out.
     *
     * @param string[] $axisCodes
     * @return array<string, array{values: string[]}>
     */
    private function attributeValues(Product $product, array $axisCodes): array
    {
        $this->attributeCodes ??= $this->attributes->groupAttributeCodes() ?? [];

        $result = [];
        foreach ($this->attributeCodes as $code) {
            if (str_starts_with($code, 'mercur_') || in_array($code, $axisCodes, true)) {
                continue;
            }
            $raw = $product->getData($code);
            if ($raw === null || $raw === '' || $raw === false) {
                continue;
            }
            $input = $this->frontendInput($code);
            if ($input === 'select' || $input === 'multiselect') {
                $values = (array) $product->getAttributeText($code);
            } elseif ($input === 'boolean') {
                $values = [(int) $raw ? '1' : '0'];
            } else {
                $values = [(string) $raw];
            }
            $values = array_values(array_filter(array_map('strval', $values), static fn ($v) => $v !== ''));
            if ($values !== []) {
                $result[$code] = ['values' => $values];
            }
        }

        return $result;
    }

    private function frontendInput(string $code): string
    {
        try {
            return (string) $this->attributeRepository->get($code)->getFrontendInput() ?: 'text';
        } catch (NoSuchEntityException) {
            return 'text';
        }
    }
}
