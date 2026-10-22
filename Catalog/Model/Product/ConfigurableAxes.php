<?php

declare(strict_types=1);

namespace Mercur\Catalog\Model\Product;

/**
 * The attribute handles that split a Mercur product into variants. A product
 * becomes a Magento configurable only when it has several variants and every
 * axis is a global attribute (an inline, product-scoped axis has no Magento
 * attribute to configure on).
 */
final class ConfigurableAxes
{
    /**
     * @return string[]|null axis handles, or null when the product stays a set of simple products
     */
    public function fromProduct(array $mercurProduct): ?array
    {
        $variants = array_filter($mercurProduct['variants'] ?? [], static fn ($v) => !empty($v['sku']));
        if (count($variants) < 2) {
            return null;
        }

        $handles = [];
        foreach ($mercurProduct['attributes'] ?? [] as $attribute) {
            if (empty($attribute['is_variant_axis'])) {
                continue;
            }
            if (!empty($attribute['is_scoped']) || empty($attribute['handle'])) {
                return null;
            }
            $handles[] = (string) $attribute['handle'];
        }

        return $handles === [] ? null : $handles;
    }
}
