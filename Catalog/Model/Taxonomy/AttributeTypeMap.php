<?php

declare(strict_types=1);

namespace Mercur\Catalog\Model\Taxonomy;

/**
 * Magento EAV frontend input <-> Mercur attribute type.
 */
class AttributeTypeMap
{
    private const TO_MERCUR = [
        'select' => 'single_select',
        'multiselect' => 'multi_select',
        'boolean' => 'toggle',
        'text' => 'text',
        'textarea' => 'text',
        'price' => 'unit',
        'weight' => 'unit',
    ];

    private const TO_MAGENTO = [
        'single_select' => 'select',
        'multi_select' => 'multiselect',
        'toggle' => 'boolean',
        'text' => 'text',
        'unit' => 'text',
    ];

    public function toMercur(string $frontendInput, bool $isVariantAxis): string
    {
        if ($isVariantAxis) {
            return 'multi_select';
        }

        return self::TO_MERCUR[$frontendInput] ?? 'text';
    }

    public function toMagento(string $mercurType): string
    {
        return self::TO_MAGENTO[$mercurType] ?? 'text';
    }
}
