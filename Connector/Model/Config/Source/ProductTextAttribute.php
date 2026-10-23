<?php

declare(strict_types=1);

namespace Mercur\Connector\Model\Config\Source;

use Magento\Catalog\Api\ProductAttributeRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Data\OptionSourceInterface;

/**
 * The operator's own text attributes, the ones that can hold an EAN, UPC or GTIN.
 */
class ProductTextAttribute implements OptionSourceInterface
{
    public function __construct(
        private readonly ProductAttributeRepositoryInterface $attributes,
        private readonly SearchCriteriaBuilder $criteria
    ) {
    }

    public function toOptionArray(): array
    {
        $criteria = $this->criteria
            ->addFilter('frontend_input', ['text', 'textarea'], 'in')
            ->addFilter('is_user_defined', 1)
            ->create();

        $options = [];
        foreach ($this->attributes->getList($criteria)->getItems() as $attribute) {
            $code = (string) $attribute->getAttributeCode();
            $options[] = [
                'value' => $code,
                'label' => sprintf('%s (%s)', (string) $attribute->getDefaultFrontendLabel() ?: $code, $code),
            ];
        }
        usort($options, static fn (array $a, array $b) => strcmp($a['label'], $b['label']));

        return $options;
    }
}
