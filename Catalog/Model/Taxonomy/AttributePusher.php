<?php

declare(strict_types=1);

namespace Mercur\Catalog\Model\Taxonomy;

use Magento\Catalog\Api\ProductAttributeRepositoryInterface;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
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
        private readonly AttributeGroup $group,
        private readonly AttributeCollectionFactory $attributes,
        private readonly ProductAttributeRepositoryInterface $attributeRepository,
        private readonly AttributeTypeMap $types,
        private readonly Configurable $configurableType
    ) {
    }

    public function pushAll(callable $log): int
    {
        $groupId = $this->group->find();
        if ($groupId === null) {
            $log('No attribute group named ' . $this->config->getAttributeGroup() . ' in the configured attribute set');

            return 0;
        }

        $collection = $this->attributes->create();
        $collection->setAttributeGroupFilter($groupId);

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
        $existing = $this->api->findAttributeByHandle($code);
        // Magento 2 has no is_configurable flag: an attribute is an axis when a configurable can use it,
        // which like the configurable wizard also requires a dropdown (booleans have a source too).
        // An axis set in Mercur stays one, since its products already depend on it.
        $isAxis = ($attribute->getFrontendInput() === 'select' && $this->configurableType->canUseAttribute($attribute))
            || !empty($existing['is_variant_axis']);

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
