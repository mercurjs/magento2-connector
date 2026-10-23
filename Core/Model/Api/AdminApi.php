<?php

declare(strict_types=1);

namespace Mercur\Core\Model\Api;

use Mercur\Core\Model\Config;

/**
 * Admin API calls the connector makes with the secret API key.
 */
class AdminApi
{
    /**
     * The offer → inventory link already yields the inventory item in `inventory_items[]`,
     * so its levels sit directly under each entry.
     */
    public const OFFER_FIELDS = '+inventory_items.location_levels.stocked_quantity,'
        . '+inventory_items.location_levels.reserved_quantity,'
        . '+seller.is_premium,+seller.status,+seller.closed_from,+seller.closed_to,'
        . '+seller.default_leadtime_to_ship,+product_variant.product_id';

    /** Variant option values are not in Mercur's default product fields; the axis values come from them. */
    public const PRODUCT_FIELDS = '+variants.options.value,+variants.options.option.title';

    public function __construct(
        private readonly Client $client,
        private readonly Config $config
    ) {
    }

    public function listOffers(?string $updatedSince, int $offset, ?int $limit = null): array
    {
        return $this->client->get('/admin/offers', [
            'updated_at' => $updatedSince ? ['$gte' => $updatedSince] : null,
            'with_deleted' => 'true',
            'order' => 'updated_at',
            'limit' => $limit ?? $this->config->getPageSize(),
            'offset' => $offset,
            'fields' => self::OFFER_FIELDS,
        ], $this->client->adminHeaders());
    }

    public function listSellers(?string $updatedSince, int $offset, ?int $limit = null): array
    {
        return $this->client->get('/admin/sellers', [
            'updated_at' => $updatedSince ? ['$gte' => $updatedSince] : null,
            'with_deleted' => 'true',
            'order' => 'updated_at',
            'limit' => $limit ?? $this->config->getPageSize(),
            'offset' => $offset,
            'fields' => 'id,name,handle,status,is_premium,closed_from,closed_to,default_leadtime_to_ship,'
                . 'description,logo,banner,created_at,updated_at,deleted_at,address.city,address.country_code',
        ], $this->client->adminHeaders());
    }

    public function listPublishedProducts(?string $updatedSince, int $offset, ?int $limit = null): array
    {
        return $this->client->get('/admin/products', [
            'status' => ['published'],
            'updated_at' => $updatedSince ? ['$gte' => $updatedSince] : null,
            'order' => 'updated_at',
            'limit' => $limit ?? $this->config->getPageSize(),
            'offset' => $offset,
            'fields' => self::PRODUCT_FIELDS,
        ], $this->client->adminHeaders());
    }

    public function findCategoryByHandle(string $handle): ?array
    {
        $result = $this->client->get('/admin/product-categories', ['handle' => $handle, 'limit' => 1], $this->client->adminHeaders());

        return $result['product_categories'][0] ?? null;
    }

    public function createCategory(array $payload): array
    {
        return $this->client->post('/admin/product-categories', $payload, $this->client->adminHeaders())['product_category'] ?? [];
    }

    public function updateCategory(string $id, array $payload): array
    {
        return $this->client->post('/admin/product-categories/' . $id, $payload, $this->client->adminHeaders())['product_category'] ?? [];
    }

    public function findAttributeByHandle(string $handle): ?array
    {
        $result = $this->client->get('/admin/product-attributes', ['handle' => $handle, 'limit' => 1], $this->client->adminHeaders());

        return $result['product_attributes'][0] ?? null;
    }

    public function createAttribute(array $payload): array
    {
        return $this->client->post('/admin/product-attributes', $payload, $this->client->adminHeaders())['product_attribute'] ?? [];
    }

    public function updateAttribute(string $id, array $payload): array
    {
        return $this->client->post('/admin/product-attributes/' . $id, $payload, $this->client->adminHeaders())['product_attribute'] ?? [];
    }

    public function createAttributeValue(string $attributeId, array $payload): array
    {
        return $this->client->post('/admin/product-attributes/' . $attributeId . '/values', $payload, $this->client->adminHeaders());
    }

    public function getOrder(string $orderId, string $fields): array
    {
        return $this->client->get('/admin/orders/' . $orderId, ['fields' => $fields], $this->client->adminHeaders())['order'] ?? [];
    }

    public function recordExternalRefund(string $orderId, float $amount, string $reference, ?string $note = null): array
    {
        return $this->client->post('/admin/magento/orders/' . $orderId . '/refunds/external', array_filter([
            'amount' => $amount,
            'reference' => $reference,
            'note' => $note,
        ]), $this->client->adminHeaders());
    }

    public function captureGroupPayment(string $groupId, string $reference): array
    {
        return $this->client->post('/admin/magento/order-groups/' . $groupId . '/payments/capture', [
            'reference' => $reference,
        ], $this->client->adminHeaders())['payment'] ?? [];
    }

    public function cancelGroupPayment(string $groupId, string $reference): array
    {
        return $this->client->post('/admin/magento/order-groups/' . $groupId . '/payments/cancel', [
            'reference' => $reference,
        ], $this->client->adminHeaders())['payment'] ?? [];
    }

    public function status(?int $storeId = null): array
    {
        return $this->client->get('/admin/magento/status', [], $this->client->adminHeaders($storeId), $storeId);
    }

    public function getRegion(string $regionId, ?int $storeId = null): array
    {
        try {
            return $this->client->get('/admin/regions/' . $regionId, ['fields' => 'id,name,currency_code'], $this->client->adminHeaders($storeId), $storeId)['region'] ?? [];
        } catch (ApiException $e) {
            if ($e->getStatus() === 404) {
                return [];
            }
            throw $e;
        }
    }
}
