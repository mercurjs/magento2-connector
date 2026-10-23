<?php

declare(strict_types=1);

namespace Mercur\Catalog\Model\Product;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductAttributeRepositoryInterface;
use Magento\Eav\Api\AttributeOptionManagementInterface;
use Magento\Eav\Api\Data\AttributeOptionInterfaceFactory;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Writes the wrapped `attributes` array of a Mercur product onto a Magento
 * product: global attributes map to the EAV attribute with the same code,
 * one-off scoped attributes are kept verbatim in `mercur_scoped_attributes`.
 */
class AttributeWriter
{
    public function __construct(
        private readonly ProductAttributeRepositoryInterface $attributes,
        private readonly AttributeOptionManagementInterface $options,
        private readonly AttributeOptionInterfaceFactory $optionFactory
    ) {
    }

    public function apply(ProductInterface $product, array $wrapped, array $variant): void
    {
        $scoped = [];
        foreach ($wrapped as $attribute) {
            $handle = (string) ($attribute['handle'] ?? '');
            $values = array_map(static fn ($v) => (string) $v['name'], $attribute['values'] ?? []);
            if (!empty($attribute['is_variant_axis'])) {
                $values = VariantAxisValues::forVariant($attribute, $variant) ?: $values;
            }

            if (!empty($attribute['is_scoped']) || $handle === '') {
                $scoped[] = ['name' => $attribute['name'] ?? $handle, 'values' => $values];
                continue;
            }

            try {
                $eav = $this->attributes->get($handle);
            } catch (NoSuchEntityException) {
                $scoped[] = ['name' => $attribute['name'] ?? $handle, 'values' => $values];
                continue;
            }

            $input = (string) $eav->getFrontendInput();
            if ($input === 'select' || $input === 'multiselect') {
                $ids = array_map(fn (string $label) => $this->optionId($eav->getAttributeCode(), $label), $values);
                $ids = array_filter($ids);
                $product->setCustomAttribute($handle, $input === 'select' ? (reset($ids) ?: null) : implode(',', $ids));
            } elseif ($input === 'boolean') {
                $value = strtolower((string) (reset($values) ?: ''));
                $product->setCustomAttribute($handle, in_array($value, ['1', 'true', 'yes'], true) ? 1 : 0);
            } else {
                $product->setCustomAttribute($handle, implode(', ', $values));
            }
        }

        $product->setCustomAttribute('mercur_scoped_attributes', $scoped === [] ? null : json_encode($scoped));
    }

    private function optionId(string $attributeCode, string $label): ?string
    {
        $attribute = $this->attributes->get($attributeCode);
        foreach ($attribute->getOptions() ?? [] as $option) {
            if ((string) $option->getLabel() === $label) {
                return (string) $option->getValue();
            }
        }

        $option = $this->optionFactory->create();
        $option->setLabel($label);
        $this->options->add(\Magento\Catalog\Api\Data\ProductAttributeInterface::ENTITY_TYPE_CODE, $attributeCode, $option);

        $attribute = $this->attributes->get($attributeCode);
        foreach ($attribute->getOptions() ?? [] as $refreshed) {
            if ((string) $refreshed->getLabel() === $label) {
                return (string) $refreshed->getValue();
            }
        }

        return null;
    }
}
