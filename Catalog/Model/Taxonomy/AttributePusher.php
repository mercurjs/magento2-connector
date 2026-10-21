<?php

declare(strict_types=1);

namespace Mercur\Catalog\Model\Taxonomy;

use Magento\Catalog\Api\ProductAttributeRepositoryInterface;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\Group\CollectionFactory as GroupCollectionFactory;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\CollectionFactory as AttributeCollectionFactory;
use Mercur\Catalog\Model\CatalogConfig;
use Mercur\Core\Model\Api\AdminApi;

/**
 * Pushes every attribute in the configured attribute group as a Mercur global
 * attribute keyed by attribute code, with its options as values.
 */
class AttributePusher
{
    public function __construct(
        private readonly AdminApi $api,
        private readonly CatalogConfig $config,
        private readonly GroupCollectionFactory $groups,
        private readonly AttributeCollectionFactory $attributes,
        private readonly ProductAttributeRepositoryInterface $attributeRepository,
        private readonly AttributeTypeMap $types
    ) {
    }

    public function pushAll(callable $log): int
    {
        $group = $this->groups->create()
            ->addFieldToFilter('attribute_set_id', $this->config->getAttributeSetId())
            ->addFieldToFilter('attribute_group_name', $this->config->getAttributeGroup())
            ->getFirstItem();
        if (!$group->getId()) {
            $log('No attribute group named ' . $this->config->getAttributeGroup() . ' in the configured attribute set');

            return 0;
        }

        $collection = $this->attributes->create();
        $collection->setAttributeGroupFilter((int) $group->getId());

        $count = 0;
        foreach ($collection as $row) {
            $attribute = $this->attributeRepository->get((string) $row->getAttributeCode());
            $this->push($attribute);
            $count++;
        }
        $log("Pushed $count attribute(s)");

        return $count;
    }

    public function push(Attribute $attribute): ?string
    {
        $code = (string) $attribute->getAttributeCode();
        $isAxis = (bool) $attribute->getData('is_global') && in_array($attribute->getFrontendInput(), ['select'], true)
            && (bool) $attribute->getData('is_configurable');

        $values = [];
        if (in_array($attribute->getFrontendInput(), ['select', 'multiselect'], true)) {
            foreach ($attribute->getSource()->getAllOptions(false) as $rank => $option) {
                if ((string) $option['value'] === '') {
                    continue;
                }
                $values[] = [
                    'name' => (string) $option['label'],
                    'rank' => (int) $rank,
                    'metadata' => ['magento_option_id' => (string) $option['value']],
                ];
            }
        }

        $payload = [
            'name' => (string) $attribute->getDefaultFrontendLabel(),
            'handle' => $code,
            'type' => $this->types->toMercur((string) $attribute->getFrontendInput(), $isAxis),
            'is_required' => (bool) $attribute->getIsRequired(),
            'is_filterable' => (bool) $attribute->getIsFilterableInSearch() || (bool) $attribute->getIsFilterable(),
            'is_variant_axis' => $isAxis,
            'rank' => (int) $attribute->getPosition(),
            'metadata' => ['magento_attribute_id' => (int) $attribute->getAttributeId()],
        ];

        $existing = $this->api->findAttributeByHandle($code);
        if ($existing) {
            $this->api->updateAttribute((string) $existing['id'], $payload);
            $known = array_map(static fn ($v) => $v['name'], $existing['values'] ?? []);
            foreach ($values as $value) {
                if (!in_array($value['name'], $known, true)) {
                    $this->api->createAttributeValue((string) $existing['id'], $value);
                }
            }

            return (string) $existing['id'];
        }

        $created = $this->api->createAttribute($payload + ['values' => $values]);

        return isset($created['id']) ? (string) $created['id'] : null;
    }
}
