<?php

declare(strict_types=1);

namespace Mercur\Catalog\Model\Product;

/**
 * The attributes that split a Mercur product into variants. A product
 * becomes a Magento configurable only when it has several variants and every
 * axis is a global attribute (an inline, product-scoped axis has no Magento
 * attribute to configure on).
 */
final class ConfigurableAxes
{
    /**
     * @return array<int, array>|null axis attribute entries, or null when the product stays a set of simple products
     */
    public function fromProduct(array $mercurProduct): ?array
    {
        if ($this->variantCount($mercurProduct) < 2) {
            return null;
        }

        $axes = [];
        foreach ($mercurProduct['attributes'] ?? [] as $attribute) {
            if (empty($attribute['is_variant_axis'])) {
                continue;
            }
            if (!empty($attribute['is_scoped']) || empty($attribute['handle'])) {
                return null;
            }
            $axes[] = $attribute;
        }

        return $axes === [] ? null : $axes;
    }

    public function hasInlineAxis(array $mercurProduct): bool
    {
        if ($this->variantCount($mercurProduct) < 2) {
            return false;
        }
        foreach ($mercurProduct['attributes'] ?? [] as $attribute) {
            if (!empty($attribute['is_variant_axis']) && (!empty($attribute['is_scoped']) || empty($attribute['handle']))) {
                return true;
            }
        }

        return false;
    }

    private function variantCount(array $mercurProduct): int
    {
        return count(array_filter($mercurProduct['variants'] ?? [], static fn ($v) => !empty($v['sku'])));
    }
}
