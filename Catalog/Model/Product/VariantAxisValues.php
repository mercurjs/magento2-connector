<?php

declare(strict_types=1);

namespace Mercur\Catalog\Model\Product;

/**
 * A variant's own value for an axis comes from its option values, not from
 * the product-level selection of every value. Mercur titles the option after
 * the attribute name, so both the name and the handle are accepted.
 */
final class VariantAxisValues
{
    /**
     * @return string[]
     */
    public static function forVariant(array $axis, array $variant): array
    {
        $names = array_filter([
            strtolower((string) ($axis['name'] ?? '')),
            strtolower((string) ($axis['handle'] ?? '')),
        ]);

        $result = [];
        foreach ($variant['options'] ?? [] as $option) {
            $title = strtolower((string) ($option['option']['title'] ?? $option['option_title'] ?? ''));
            if ($title !== '' && in_array($title, $names, true) && isset($option['value'])) {
                $result[] = (string) $option['value'];
            }
        }

        return $result;
    }
}
