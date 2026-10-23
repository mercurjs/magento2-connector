<?php

declare(strict_types=1);

namespace Mercur\Catalog\Model\Product;

/**
 * Turns a snapshot of a Magento product (see ExportSnapshot) into Mercur admin
 * API payloads. Global attributes are referenced by id and their values by
 * name; a configurable axis with no Mercur variant-axis attribute behind it is
 * sent inline. Updates only add: no variant, attribute or value is removed,
 * so seller offers on a variant never lose it.
 */
final class ExportPayload
{
    public const SOURCE = 'magento';

    /**
     * @param array<string, array|null> $mercurAttributes attribute code => Mercur attribute, null when Mercur has none
     */
    public function create(array $snapshot, array $mercurAttributes): array
    {
        $refs = [];
        foreach ($snapshot['attributes'] as $code => $attribute) {
            $ref = $this->valueRef($mercurAttributes[$code] ?? null, $attribute['values']);
            if ($ref !== null) {
                $refs[] = $ref;
            }
        }
        foreach ($snapshot['axes'] as $code => $label) {
            $values = $this->axisValues($snapshot, $code);
            $global = $this->globalAxis($mercurAttributes[$code] ?? null);
            if ($global === null) {
                $refs[] = ['title' => $label, 'type' => 'multi_select', 'values' => $values, 'is_variant_axis' => true];
                continue;
            }
            $ids = $this->valueIds($global, $values);
            if (count($ids) !== count($values)) {
                // Mercur lacks some of the values, so the axis cannot be referenced by id.
                $refs[] = ['title' => $label, 'type' => 'multi_select', 'values' => $values, 'is_variant_axis' => true];
                continue;
            }
            $refs[] = ['id' => (string) $global['id'], 'value_ids' => $ids];
        }

        $variants = [];
        foreach ($snapshot['variants'] as $variant) {
            $variants[] = $this->variant($variant, $snapshot) + ['sku' => $variant['sku']]
                + $this->variantOptions($variant, $snapshot, $mercurAttributes);
        }

        return array_filter($this->product($snapshot), static fn ($v) => $v !== null) + array_filter([
            'attributes' => $refs,
            'variants' => $variants,
        ]);
    }

    /**
     * @param array $existing the Mercur product the snapshot's SKUs belong to
     * @param array<string, array|null> $mercurAttributes
     * @return array{product: array, attributes: array, variant_updates: array<string, array>, variant_creates: array[]}
     */
    public function update(array $snapshot, array $existing, array $mercurAttributes): array
    {
        $attached = [];
        foreach ($existing['attributes'] ?? [] as $attribute) {
            $attached[(string) $attribute['id']] = $attribute;
        }
        $inlineAxes = [];
        foreach ($attached as $attribute) {
            if (!empty($attribute['is_variant_axis']) && !empty($attribute['is_scoped'])) {
                $inlineAxes[strtolower((string) ($attribute['name'] ?? ''))] = $attribute;
            }
        }

        $add = [];
        $update = [];
        foreach ($snapshot['attributes'] as $code => $attribute) {
            $ref = $this->valueRef($mercurAttributes[$code] ?? null, $attribute['values']);
            if ($ref === null) {
                continue;
            }
            if (isset($attached[$ref['id']])) {
                $update[] = $ref;
            } else {
                $add[] = $ref;
            }
        }
        foreach ($snapshot['axes'] as $code => $label) {
            $values = $this->axisValues($snapshot, $code);
            $global = $this->globalAxis($mercurAttributes[$code] ?? null);
            if ($global !== null && isset($attached[(string) $global['id']])) {
                $selected = array_map(static fn ($v) => (string) $v['id'], $attached[(string) $global['id']]['values'] ?? []);
                $ids = array_values(array_unique(array_merge($selected, $this->valueIds($global, $values))));
                if (count($ids) > count($selected)) {
                    $update[] = ['id' => (string) $global['id'], 'value_ids' => $ids];
                }
                continue;
            }
            $inline = $inlineAxes[strtolower($label)] ?? null;
            if ($inline !== null) {
                $known = array_map(static fn ($v) => strtolower((string) $v['name']), $inline['all_values'] ?? []);
                $missing = array_values(array_filter($values, static fn ($v) => !in_array(strtolower($v), $known, true)));
                if ($missing !== []) {
                    $update[] = ['id' => (string) $inline['id'], 'add' => $missing];
                }
            }
        }

        $variantIds = [];
        foreach ($existing['variants'] ?? [] as $variant) {
            if (!empty($variant['sku'])) {
                $variantIds[(string) $variant['sku']] = (string) $variant['id'];
            }
        }
        $updates = [];
        $creates = [];
        foreach ($snapshot['variants'] as $variant) {
            $payload = $this->variant($variant, $snapshot);
            if (isset($variantIds[$variant['sku']])) {
                $updates[$variantIds[$variant['sku']]] = $payload;
            } else {
                $creates[] = $payload + ['sku' => $variant['sku']]
                    + $this->variantOptions($variant, $snapshot, $mercurAttributes);
            }
        }

        return [
            'product' => $this->product($snapshot),
            'attributes' => array_filter(['add' => $add, 'update' => $update]),
            'variant_updates' => $updates,
            'variant_creates' => $creates,
        ];
    }

    private function product(array $snapshot): array
    {
        return [
            'title' => $snapshot['title'],
            'description' => $snapshot['description'],
            'handle' => $snapshot['handle'],
            'status' => $snapshot['enabled'] ? 'published' : 'draft',
            'external_id' => (string) $snapshot['magento_id'],
            'weight' => $snapshot['weight'],
            'thumbnail' => $snapshot['images'][0] ?? null,
            'images' => array_map(static fn (string $url) => ['url' => $url], $snapshot['images']),
            'categories' => array_map(static fn (string $id) => ['id' => $id], $snapshot['category_ids']),
            'metadata' => [
                'source' => self::SOURCE,
                'magento_id' => $snapshot['magento_id'],
                'magento_sku' => $snapshot['sku'],
            ],
        ];
    }

    private function variant(array $variant, array $snapshot): array
    {
        $title = $variant['title'];
        if ($snapshot['axes'] !== []) {
            $title = implode(' / ', array_map(static fn (string $code) => $variant['axes'][$code] ?? '', array_keys($snapshot['axes'])));
        }

        return [
            'title' => $title,
            'weight' => $variant['weight'],
            'metadata' => ['magento_id' => $variant['magento_id']],
        ];
    }

    /**
     * Variant options keyed by option title: a global axis is titled after the
     * Mercur attribute, an inline one after the Magento label it was sent with.
     * A product without axes sends none, and Mercur gives it its default option.
     *
     * @return array{options?: array<string, string>}
     */
    private function variantOptions(array $variant, array $snapshot, array $mercurAttributes): array
    {
        if ($snapshot['axes'] === []) {
            return [];
        }
        $options = [];
        foreach ($snapshot['axes'] as $code => $label) {
            $global = $this->globalAxis($mercurAttributes[$code] ?? null);
            $values = $this->axisValues($snapshot, $code);
            $title = $global !== null && count($this->valueIds($global, $values)) === count($values)
                ? (string) $global['name']
                : $label;
            $options[$title] = (string) ($variant['axes'][$code] ?? '');
        }

        return ['options' => $options];
    }

    /**
     * A reference to a global, non-axis attribute with the product's values,
     * or null when Mercur has no such attribute or none of the values.
     *
     * @param string[] $values
     */
    private function valueRef(?array $mercur, array $values): ?array
    {
        if ($mercur === null || $values === [] || !empty($mercur['is_variant_axis'])) {
            return null;
        }
        $id = (string) $mercur['id'];
        switch ($mercur['type'] ?? 'text') {
            case 'single_select':
                $ids = array_slice($this->valueIds($mercur, $values), 0, 1);

                return $ids === [] ? null : ['id' => $id, 'value_ids' => $ids];
            case 'multi_select':
                $ids = $this->valueIds($mercur, $values);

                return $ids === [] ? null : ['id' => $id, 'value_ids' => $ids];
            case 'toggle':
                return ['id' => $id, 'value' => in_array(strtolower($values[0]), ['1', 'yes', 'true'], true)];
            case 'unit':
                return is_numeric($values[0]) ? ['id' => $id, 'value' => (float) $values[0]] : null;
            default:
                return ['id' => $id, 'value' => implode(', ', $values)];
        }
    }

    private function globalAxis(?array $mercur): ?array
    {
        return $mercur !== null && !empty($mercur['is_variant_axis']) && ($mercur['type'] ?? null) === 'multi_select'
            ? $mercur
            : null;
    }

    /**
     * @param string[] $names
     * @return string[] ids of the Mercur values whose name matches, in the order given
     */
    private function valueIds(array $mercur, array $names): array
    {
        $byName = [];
        foreach ($mercur['values'] ?? [] as $value) {
            $byName[strtolower(trim((string) $value['name']))] = (string) $value['id'];
        }
        $ids = [];
        foreach ($names as $name) {
            $id = $byName[strtolower(trim($name))] ?? null;
            if ($id !== null) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return string[] the distinct labels the variants take on one axis
     */
    private function axisValues(array $snapshot, string $code): array
    {
        $values = [];
        foreach ($snapshot['variants'] as $variant) {
            $value = (string) ($variant['axes'][$code] ?? '');
            if ($value !== '') {
                $values[$value] = true;
            }
        }

        return array_keys($values);
    }
}
