<?php

declare(strict_types=1);

namespace Mercur\Connector\Model\Checkout;

use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Model\Quote\Item;
use Mercur\Core\Model\Api\StoreApi;
use Mercur\Core\Model\Config;

/**
 * Keeps a Mercur cart in step with the marketplace lines of a Magento quote.
 * First-party lines never reach Mercur.
 */
class ShadowCart
{
    public function __construct(
        private readonly StoreApi $api,
        private readonly Config $config,
        private readonly CartRepositoryInterface $quotes
    ) {
    }

    /**
     * @return Item[]
     */
    public function marketplaceItems(Quote $quote): array
    {
        return array_values(array_filter(
            $quote->getAllVisibleItems(),
            static fn (Item $item) => (bool) $item->getData('mercur_offer_id')
        ));
    }

    public function hasMarketplaceItems(Quote $quote): bool
    {
        return $this->marketplaceItems($quote) !== [];
    }

    /**
     * Creates the Mercur cart on first use and makes its line items match the quote.
     * Returns null when the quote holds no marketplace line.
     */
    public function sync(Quote $quote): ?array
    {
        $items = $this->marketplaceItems($quote);
        $storeId = (int) $quote->getStoreId();
        $cartId = (string) $quote->getData('mercur_cart_id');

        if ($items === []) {
            return null;
        }

        if ($cartId === '') {
            $cart = $this->api->createCart(
                $this->config->getRegionId($storeId),
                $this->config->getSalesChannelId($storeId),
                (string) $quote->getBaseCurrencyCode(),
                ['channel' => $this->config->getChannel($storeId), 'magento_quote_id' => (string) $quote->getId()],
                $storeId
            );
            $cartId = (string) $cart['id'];
            $quote->setData('mercur_cart_id', $cartId);
        } else {
            $cart = $this->api->getCart($cartId, $storeId);
        }

        $remote = [];
        foreach ($cart['items'] ?? [] as $line) {
            $remote[(string) $line['id']] = $line;
        }

        foreach ($items as $item) {
            $lineId = (string) $item->getData('mercur_line_item_id');
            $qty = (int) $item->getQty();
            if ($lineId !== '' && isset($remote[$lineId])) {
                if ((int) $remote[$lineId]['quantity'] !== $qty) {
                    $cart = $this->api->updateLineItem($cartId, $lineId, $qty, $storeId);
                }
                unset($remote[$lineId]);
                continue;
            }
            $cart = $this->api->addLineItem($cartId, (string) $item->getData('mercur_offer_id'), $qty, $storeId);
            $added = $this->findLine($cart, (string) $item->getData('mercur_offer_id'));
            if ($added) {
                $item->setData('mercur_line_item_id', $added['id']);
                unset($remote[(string) $added['id']]);
            }
        }

        foreach (array_keys($remote) as $staleLineId) {
            $this->api->removeLineItem($cartId, $staleLineId, $storeId);
        }

        return $this->api->getCart($cartId, $storeId);
    }

    public function syncAddress(Quote $quote, Address $address): void
    {
        $cartId = (string) $quote->getData('mercur_cart_id');
        if ($cartId === '' || !$address->getCountryId()) {
            return;
        }

        $payload = [
            'first_name' => (string) $address->getFirstname(),
            'last_name' => (string) $address->getLastname(),
            'address_1' => (string) ($address->getStreetLine(1) ?: ''),
            'address_2' => (string) ($address->getStreetLine(2) ?: ''),
            'city' => (string) $address->getCity(),
            'province' => (string) ($address->getRegionCode() ?: $address->getRegion()),
            'postal_code' => (string) $address->getPostcode(),
            'country_code' => strtolower((string) $address->getCountryId()),
            'phone' => (string) $address->getTelephone(),
        ];

        $this->api->updateCart($cartId, [
            'email' => (string) ($quote->getCustomerEmail() ?: $address->getEmail()),
            'shipping_address' => $payload,
            'billing_address' => $payload,
        ], (int) $quote->getStoreId());
    }

    /**
     * @return array<string, array<int, array>> options grouped by seller id
     */
    public function shippingOptions(Quote $quote): array
    {
        $cartId = (string) $quote->getData('mercur_cart_id');

        return $cartId === '' ? [] : $this->api->getShippingOptions($cartId, (int) $quote->getStoreId());
    }

    /**
     * Applies one option per seller: the chosen one when the shopper picked, the cheapest otherwise.
     *
     * @return array<string, array> chosen option per seller id
     */
    public function applyShippingMethods(Quote $quote, array $options): array
    {
        $cartId = (string) $quote->getData('mercur_cart_id');
        if ($cartId === '') {
            return [];
        }

        $chosen = $this->chosenShippingMethods($quote);
        $applied = [];
        foreach ($options as $sellerId => $sellerOptions) {
            if ($sellerOptions === []) {
                continue;
            }
            $pick = null;
            foreach ($sellerOptions as $option) {
                if (($chosen[$sellerId] ?? null) === $option['id']) {
                    $pick = $option;
                    break;
                }
            }
            if ($pick === null) {
                usort($sellerOptions, static fn ($a, $b) => ((float) ($a['amount'] ?? 0)) <=> ((float) ($b['amount'] ?? 0)));
                $pick = $sellerOptions[0];
            }
            $this->api->addShippingMethod($cartId, (string) $pick['id'], (int) $quote->getStoreId());
            $applied[(string) $sellerId] = $pick;
        }

        $quote->setData('mercur_shipping_methods', json_encode(array_map(static fn ($o) => $o['id'], $applied)));

        return $applied;
    }

    /**
     * @return array<string, string> seller id => option id
     */
    public function chosenShippingMethods(Quote $quote): array
    {
        $raw = $quote->getData('mercur_shipping_methods');
        $decoded = $raw ? json_decode((string) $raw, true) : [];

        return is_array($decoded) ? $decoded : [];
    }

    public function chooseShippingMethod(Quote $quote, string $sellerId, string $optionId): void
    {
        $chosen = $this->chosenShippingMethods($quote);
        $chosen[$sellerId] = $optionId;
        $quote->setData('mercur_shipping_methods', json_encode($chosen));
        $this->quotes->save($quote);
    }

    private function findLine(array $cart, string $offerId): ?array
    {
        foreach ($cart['items'] ?? [] as $line) {
            $lineOffer = $line['offer_id'] ?? ($line['metadata']['offer_id'] ?? null);
            if ($lineOffer === $offerId) {
                return $line;
            }
        }
        $items = $cart['items'] ?? [];

        return $items === [] ? null : end($items);
    }
}
