<?php

declare(strict_types=1);

namespace Mercur\Core\Model\Api;

/**
 * Store API calls for the shadow cart. Every call carries the publishable key.
 */
class StoreApi
{
    public const EXTERNAL_PROVIDER_ID = 'pp_external_external';

    public function __construct(private readonly Client $client)
    {
    }

    public function createCart(string $regionId, string $salesChannelId, string $currencyCode, array $metadata, ?int $storeId = null): array
    {
        // An empty currency would override the region's; leaving it out lets Mercur fall back to it.
        return $this->client->post('/store/carts', array_filter([
            'region_id' => $regionId,
            'sales_channel_id' => $salesChannelId,
            'currency_code' => strtolower($currencyCode),
            'metadata' => $metadata,
        ], static fn ($value) => $value !== '' && $value !== []), $this->client->storeHeaders($storeId), $storeId)['cart'] ?? [];
    }

    public function getCart(string $cartId, ?int $storeId = null): array
    {
        return $this->client->get('/store/carts/' . $cartId, [], $this->client->storeHeaders($storeId), $storeId)['cart'] ?? [];
    }

    public function updateCart(string $cartId, array $payload, ?int $storeId = null): array
    {
        return $this->client->post('/store/carts/' . $cartId, $payload, $this->client->storeHeaders($storeId), $storeId)['cart'] ?? [];
    }

    public function addLineItem(string $cartId, string $offerId, int $quantity, ?int $storeId = null): array
    {
        return $this->client->post('/store/carts/' . $cartId . '/line-items', [
            'offer_id' => $offerId,
            'quantity' => $quantity,
        ], $this->client->storeHeaders($storeId), $storeId)['cart'] ?? [];
    }

    public function updateLineItem(string $cartId, string $lineItemId, int $quantity, ?int $storeId = null): array
    {
        return $this->client->post('/store/carts/' . $cartId . '/line-items/' . $lineItemId, [
            'quantity' => $quantity,
        ], $this->client->storeHeaders($storeId), $storeId)['cart'] ?? [];
    }

    public function removeLineItem(string $cartId, string $lineItemId, ?int $storeId = null): void
    {
        $this->client->delete('/store/carts/' . $cartId . '/line-items/' . $lineItemId, $this->client->storeHeaders($storeId), $storeId);
    }

    /**
     * @return array<string, array<int, array>> shipping options grouped by seller id
     */
    public function getShippingOptions(string $cartId, ?int $storeId = null): array
    {
        return $this->client->get('/store/shipping-options', ['cart_id' => $cartId], $this->client->storeHeaders($storeId), $storeId)['shipping_options'] ?? [];
    }

    public function addShippingMethod(string $cartId, string $optionId, ?int $storeId = null): array
    {
        return $this->client->post('/store/carts/' . $cartId . '/shipping-methods', [
            'option_id' => $optionId,
        ], $this->client->storeHeaders($storeId), $storeId)['cart'] ?? [];
    }

    public function createPaymentCollection(string $cartId, ?int $storeId = null): array
    {
        return $this->client->post('/store/payment-collections', ['cart_id' => $cartId], $this->client->storeHeaders($storeId), $storeId)['payment_collection'] ?? [];
    }

    public function createExternalPaymentSession(string $paymentCollectionId, array $data, ?int $storeId = null): array
    {
        return $this->client->post('/store/payment-collections/' . $paymentCollectionId . '/payment-sessions', [
            'provider_id' => self::EXTERNAL_PROVIDER_ID,
            'data' => $data,
        ], $this->client->storeHeaders($storeId), $storeId)['payment_collection'] ?? [];
    }

    public function completeCart(string $cartId, ?int $storeId = null): array
    {
        return $this->client->post('/store/carts/' . $cartId . '/complete', [], $this->client->storeHeaders($storeId), $storeId);
    }

    /**
     * @param string[] $offerIds
     */
    public function listPricedOffers(array $offerIds, string $regionId, ?int $storeId = null): array
    {
        return $this->client->get('/store/offers', [
            'id' => array_values($offerIds),
            'region_id' => $regionId,
            'limit' => count($offerIds),
            'fields' => '+calculated_price',
        ], $this->client->storeHeaders($storeId), $storeId)['offers'] ?? [];
    }

    public function listOffersForProduct(string $productId, string $regionId, ?int $storeId = null): array
    {
        return $this->client->get('/store/offers', [
            'product_id' => $productId,
            'region_id' => $regionId,
            'limit' => 100,
        ], $this->client->storeHeaders($storeId), $storeId)['offers'] ?? [];
    }
}
