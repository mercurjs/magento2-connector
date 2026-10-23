<?php

declare(strict_types=1);

namespace Mercur\Catalog\Model\Product;

use Magento\Catalog\Api\Data\ProductInterface;
use Mercur\Core\Model\Api\AdminApi;

/**
 * Upserts a Magento product into Mercur, matched by variant SKU, so sellers
 * can list offers on the operator's catalog. A Mercur product that Magento
 * did not push (a seller's, sharing a SKU) is left alone.
 */
class Exporter
{
    /** @var array<string, array|null> attribute code => Mercur attribute, per run */
    private array $mercurAttributes = [];

    public function __construct(
        private readonly AdminApi $api,
        private readonly ExportSnapshot $snapshots,
        private readonly ExportPayload $payloads
    ) {
    }

    /**
     * @return string what happened, for the process log
     */
    public function export(ProductInterface $product): string
    {
        $snapshot = $this->snapshots->build($product);
        if ($snapshot['variants'] === []) {
            return sprintf('Skipped %s: no variant with a SKU', $snapshot['sku']);
        }

        $existing = null;
        foreach ($snapshot['variants'] as $variant) {
            $existing = $this->api->findProductBySku($variant['sku']);
            if ($existing !== null) {
                break;
            }
        }
        if ($existing !== null && ($existing['metadata']['source'] ?? null) !== ExportPayload::SOURCE) {
            return sprintf('Skipped %s: SKU already on Mercur product %s, which Magento does not own', $snapshot['sku'], $existing['id']);
        }

        $attributes = $this->resolveAttributes(array_merge(array_keys($snapshot['attributes']), array_keys($snapshot['axes'])));
        $attributes = $this->completeAxes($snapshot, $attributes);

        if ($existing === null) {
            $created = $this->api->createProduct($this->payloads->create($snapshot, $attributes));

            return sprintf('Created %s as %s', $snapshot['sku'], $created['id'] ?? '?');
        }

        $productId = (string) $existing['id'];
        $plan = $this->payloads->update($snapshot, $existing, $attributes);
        $this->api->updateProduct($productId, $plan['product']);
        if ($plan['attributes'] !== []) {
            $this->api->batchProductAttributes($productId, $plan['attributes']);
        }
        foreach ($plan['variant_updates'] as $variantId => $payload) {
            $this->api->updateProductVariant($productId, (string) $variantId, $payload);
        }
        foreach ($plan['variant_creates'] as $payload) {
            $this->api->createProductVariant($productId, $payload);
        }

        return sprintf('Updated %s (%s), %d new variant(s)', $snapshot['sku'], $productId, count($plan['variant_creates']));
    }

    /**
     * Moves the Mercur products behind these SKUs back to draft. Products are
     * not deleted, so offers and past orders keep their variant.
     *
     * @param string[] $skus
     * @return string[] ids of the products archived
     */
    public function archive(array $skus): array
    {
        $archived = [];
        foreach ($skus as $sku) {
            $product = $this->api->findProductBySku((string) $sku);
            if ($product === null
                || isset($archived[$product['id']])
                || ($product['metadata']['source'] ?? null) !== ExportPayload::SOURCE
            ) {
                continue;
            }
            if (($product['status'] ?? null) !== 'draft') {
                $this->api->updateProduct((string) $product['id'], ['status' => 'draft']);
            }
            $archived[$product['id']] = (string) $product['id'];
        }

        return array_values($archived);
    }

    /**
     * Adds the axis values a configurable uses but a global Mercur axis lacks,
     * as the taxonomy push does for attribute options, so the product shares
     * the global axis instead of getting an inline one of its own.
     *
     * @param array<string, array|null> $attributes
     * @return array<string, array|null>
     */
    private function completeAxes(array $snapshot, array $attributes): array
    {
        foreach (array_keys($snapshot['axes']) as $code) {
            $mercur = $attributes[$code] ?? null;
            if ($mercur === null || empty($mercur['is_variant_axis']) || ($mercur['type'] ?? null) !== 'multi_select') {
                continue;
            }
            $known = array_map(static fn ($v) => strtolower(trim((string) $v['name'])), $mercur['values'] ?? []);
            $added = false;
            foreach ($snapshot['variants'] as $variant) {
                $label = trim((string) ($variant['axes'][$code] ?? ''));
                if ($label !== '' && !in_array(strtolower($label), $known, true)) {
                    $this->api->createAttributeValue((string) $mercur['id'], ['name' => $label]);
                    $known[] = strtolower($label);
                    $added = true;
                }
            }
            if ($added) {
                $this->mercurAttributes[$code] = $attributes[$code] = $this->api->findAttributeByHandle($code);
            }
        }

        return $attributes;
    }

    /**
     * @param string[] $codes
     * @return array<string, array|null>
     */
    private function resolveAttributes(array $codes): array
    {
        $result = [];
        foreach ($codes as $code) {
            if (!array_key_exists($code, $this->mercurAttributes)) {
                $this->mercurAttributes[$code] = $this->api->findAttributeByHandle($code);
            }
            $result[$code] = $this->mercurAttributes[$code];
        }

        return $result;
    }
}
