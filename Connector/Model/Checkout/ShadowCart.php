<?php

declare(strict_types=1);

namespace Mercur\Connector\Model\Checkout;

use Magento\Framework\DataObject;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;
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
        private readonly CartRepositoryInterface $quotes,
        private readonly ManagerInterface $events
    ) {
    }

    /**
     * @return Item[]
     */
    public function marketplaceItems(Quote $quote): array
    {
        return array_values(array_filter(
            $quote->getAllVisibleItems(),
            static fn (Item $item) => (bool) $item->getData('mercur_offer_id') && !$item->isDeleted()
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

        $lines = new DataObject(['items' => $items]);
        $this->events->dispatch('mercur_cart_sync_before', ['quote' => $quote, 'items' => $lines]);
        $items = $lines->getData('items');

        if ($cartId === '') {
            $cart = $this->api->createCart(
                $this->config->getRegionId($storeId),
                $this->config->getSalesChannelId($storeId),
                // A quote that has not been saved yet has no base currency; the store's is the same one.
                (string) ($quote->getBaseCurrencyCode() ?: $quote->getStore()->getBaseCurrencyCode()),
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

        $changed = false;
        $used = [];
        foreach ($items as $item) {
            $lineId = (string) $item->getData('mercur_line_item_id');
            $qty = (int) $item->getQty();
            if ($lineId !== '' && isset($remote[$lineId])) {
                if ((int) $remote[$lineId]['quantity'] !== $qty) {
                    $cart = $this->api->updateLineItem($cartId, $lineId, $qty, $storeId);
                    $changed = true;
                }
                unset($remote[$lineId]);
                $used[$lineId] = true;
                continue;
            }
            $cart = $this->api->addLineItem($cartId, (string) $item->getData('mercur_offer_id'), $qty, $storeId);
            $changed = true;
            $added = self::findLine($cart, (string) $item->getData('mercur_offer_id'), $lineId, $used);
            if ($added) {
                $item->setData('mercur_line_item_id', $added['id']);
                unset($remote[(string) $added['id']]);
                $used[(string) $added['id']] = true;
            }
        }

        foreach (array_keys($remote) as $staleLineId) {
            $this->api->removeLineItem($cartId, $staleLineId, $storeId);
            $changed = true;
        }

        return $changed ? $this->api->getCart($cartId, $storeId) : $cart;
    }

    public function syncAddress(Quote $quote, Address $address): void
    {
        $cartId = (string) $quote->getData('mercur_cart_id');
        if ($cartId === '' || !$address->getCountryId()) {
            return;
        }

        $billing = $quote->getBillingAddress();
        $shipping = AddressMapper::map($address->getData());
        $payload = [
            'email' => AddressMapper::email($quote->getCustomerEmail(), $address->getEmail(), $billing?->getEmail()),
            'shipping_address' => $shipping,
            'billing_address' => $billing && $billing->getCountryId() ? AddressMapper::map($billing->getData()) : $shipping,
            'metadata' => [
                'magento_customer_id' => $quote->getCustomerId() ? (string) $quote->getCustomerId() : null,
                'magento_customer_email' => (string) $quote->getCustomerEmail(),
            ],
        ];
        if ($payload['email'] === '') {
            unset($payload['email']);
        }

        $this->api->updateCart($cartId, $payload, (int) $quote->getStoreId());
    }

    /**
     * @return array<string, string> the address fields that change a shipping quote
     */
    public function addressFingerprint(Address $address): array
    {
        return array_filter(AddressMapper::map($address->getData()), static fn ($v) => $v !== '');
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
     * Options the Mercur cart already carries are not sent again.
     *
     * @param array<string, array<int, array>> $options
     * @param array<int, string> $currentOptionIds option ids already on the Mercur cart
     * @return array<string, array> chosen option per seller id
     */
    public function applyShippingMethods(Quote $quote, array $options, array $currentOptionIds = []): array
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
            if (!in_array((string) $pick['id'], $currentOptionIds, true)) {
                $this->api->addShippingMethod($cartId, (string) $pick['id'], (int) $quote->getStoreId());
            }
            $applied[(string) $sellerId] = $pick;
        }

        $this->rememberApplied($quote, $applied);

        return $applied;
    }

    /**
     * @return array<string, string> seller id => option id
     */
    public function chosenShippingMethods(Quote $quote): array
    {
        $result = [];
        foreach ($this->storedShippingMethods($quote) as $sellerId => $method) {
            $optionId = is_array($method) ? (string) ($method['option_id'] ?? '') : (string) $method;
            if ($optionId !== '') {
                $result[(string) $sellerId] = $optionId;
            }
        }

        return $result;
    }

    /**
     * The options applied on the last quote, with amounts, or null when any is missing.
     *
     * @return array<string, array{id: string, name: string, amount: float}>|null
     */
    public function appliedShippingMethods(Quote $quote): ?array
    {
        $result = [];
        foreach ($this->storedShippingMethods($quote) as $sellerId => $method) {
            if (!is_array($method) || !isset($method['amount'], $method['option_id'])) {
                return null;
            }
            $result[(string) $sellerId] = [
                'id' => (string) $method['option_id'],
                'name' => (string) ($method['name'] ?? ''),
                'amount' => (float) $method['amount'],
            ];
        }

        return $result;
    }

    /**
     * @throws LocalizedException when the seller does not offer the option
     */
    public function chooseShippingMethod(Quote $quote, string $sellerId, string $optionId): void
    {
        $offered = array_map(static fn ($option) => (string) $option['id'], $this->shippingOptions($quote)[$sellerId] ?? []);
        if (!in_array($optionId, $offered, true)) {
            throw new LocalizedException(__('Shipping option %1 is not offered by seller %2.', $optionId, $sellerId));
        }

        $stored = $this->storedShippingMethods($quote);
        $stored[$sellerId] = ['option_id' => $optionId];
        $quote->setData('mercur_shipping_methods', json_encode($stored));
        $this->quotes->save($quote);
    }

    /**
     * @param array<string, array> $applied
     */
    private function rememberApplied(Quote $quote, array $applied): void
    {
        $stored = [];
        foreach ($applied as $sellerId => $option) {
            $stored[$sellerId] = [
                'option_id' => (string) $option['id'],
                'name' => (string) ($option['name'] ?? ''),
                'amount' => (float) ($option['amount'] ?? 0),
            ];
        }
        $quote->setData('mercur_shipping_methods', json_encode($stored));
    }

    /**
     * @return array<string, mixed>
     */
    private function storedShippingMethods(Quote $quote): array
    {
        $raw = $quote->getData('mercur_shipping_methods');
        $decoded = $raw ? json_decode((string) $raw, true) : [];

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * The cart line for an offer that no other quote item has claimed yet.
     * The line the item already points at wins over the first by offer id.
     *
     * @param array<string, true> $used
     */
    public static function findLine(array $cart, string $offerId, string $preferredId = '', array $used = []): ?array
    {
        $candidates = [];
        foreach ($cart['items'] ?? [] as $line) {
            $id = (string) ($line['id'] ?? '');
            $lineOffer = $line['offer_id'] ?? ($line['metadata']['offer_id'] ?? null);
            if ($lineOffer !== $offerId || isset($used[$id])) {
                continue;
            }
            if ($preferredId !== '' && $id === $preferredId) {
                return $line;
            }
            $candidates[] = $line;
        }

        return $candidates[0] ?? null;
    }
}
