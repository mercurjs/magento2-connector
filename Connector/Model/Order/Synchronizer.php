<?php

declare(strict_types=1);

namespace Mercur\Connector\Model\Order;

use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Item;
use Mercur\Core\Model\Api\AdminApi;
use Mercur\Core\Model\Api\StoreApi;
use Mercur\Core\Model\Config;
use Psr\Log\LoggerInterface;

/**
 * Once the order is committed in Magento, the shadow cart gets an authorized
 * external payment session and is completed. Mercur splits it into one order
 * per seller; their ids are written back onto the Magento order and its items.
 * The capture is confirmed later, when an invoice is paid.
 */
class Synchronizer
{
    public function __construct(
        private readonly StoreApi $store,
        private readonly AdminApi $admin,
        private readonly Config $config,
        private readonly OrderRepositoryInterface $orders,
        private readonly Json $json,
        private readonly ManagerInterface $events,
        private readonly LoggerInterface $logger
    ) {
    }

    public function place(Order $order): void
    {
        $cartId = (string) $order->getData('mercur_cart_id');
        $items = $this->marketplaceItems($order);
        if ($cartId === '' || $items === [] || $order->getData('mercur_order_group_id')) {
            return;
        }

        $storeId = (int) $order->getStoreId();
        $this->events->dispatch('mercur_order_place_before', ['order' => $order, 'cart_id' => $cartId]);
        $cart = $this->store->getCart($cartId, $storeId);
        $this->ensureExternalPaymentSession($order, $cart, $storeId);

        $completed = $this->store->completeCart($cartId, $storeId);
        if (($completed['type'] ?? '') !== 'order_group') {
            $this->logger->error('Mercur cart completion did not return an order group', [
                'order' => $order->getIncrementId(),
                'response' => $completed,
            ]);
            return;
        }

        $group = $completed['order_group'];
        $order->setData('mercur_order_group_id', $group['id']);
        $order->setData('mercur_sent_at', gmdate('Y-m-d H:i:s'));
        $order->setData('mercur_shipping_methods', $this->json->serialize($this->shippingBySeller($order, $cart)));
        $order->addCommentToStatusHistory(__('Marketplace order group %1 created in Mercur.', $group['display_id'] ?? $group['id']));

        $children = $group['orders'] ?? [];
        if ($children === []) {
            try {
                $children = $this->admin->getOrderGroup((string) $group['id'])['orders'] ?? [];
            } catch (\Throwable $e) {
                $this->logger->warning('Could not read the orders of a Mercur order group', ['group' => $group['id'], 'exception' => $e]);
            }
        }
        $bySeller = SellerOrderMap::build($children, function (string $orderId): ?string {
            try {
                return $this->admin->getOrder($orderId, 'id,+seller.id')['seller']['id'] ?? null;
            } catch (\Throwable $e) {
                $this->logger->warning('Could not read the seller of a Mercur order', ['order' => $orderId, 'exception' => $e]);

                return null;
            }
        });

        foreach ($items as $item) {
            $sellerId = (string) $item->getData('mercur_seller_id');
            if (isset($bySeller[$sellerId])) {
                $item->setData('mercur_order_id', $bySeller[$sellerId]);
                $item->setData('mercur_status', 'not_fulfilled');
            }
        }

        foreach (array_unique($bySeller) as $sellerOrderId) {
            $this->linkLineItems($sellerOrderId, $items);
        }

        $this->orders->save($order);
        $this->events->dispatch('mercur_order_place_after', [
            'order' => $order,
            'cart_id' => $cartId,
            'order_group_id' => (string) $group['id'],
            'seller_orders' => array_values(array_unique($bySeller)),
        ]);
    }

    /**
     * A retry after a failed completion finds the collection and session from
     * the earlier attempt on the cart; Mercur allows one collection per cart.
     */
    private function ensureExternalPaymentSession(Order $order, array $cart, int $storeId): void
    {
        $collection = is_array($cart['payment_collection'] ?? null) ? $cart['payment_collection'] : [];
        foreach ($collection['payment_sessions'] ?? [] as $session) {
            if (($session['provider_id'] ?? null) === StoreApi::EXTERNAL_PROVIDER_ID) {
                return;
            }
        }

        $collectionId = (string) ($collection['id'] ?? '');
        if ($collectionId === '') {
            $collectionId = (string) ($this->store->createPaymentCollection((string) $cart['id'], $storeId)['id'] ?? '');
        }

        $this->store->createExternalPaymentSession($collectionId, [
            'reference' => (string) $order->getIncrementId(),
            'amount' => (float) ($cart['total'] ?? 0),
            'currency_code' => strtolower((string) $order->getBaseCurrencyCode()),
            'authorized_at' => gmdate(DATE_ATOM),
            'channel' => $this->config->getChannel($storeId),
            'payment_method' => (string) $order->getPayment()?->getMethod(),
        ], $storeId);
    }

    /**
     * Seller order lines are new records in Mercur; fulfillments point at
     * them, so each Magento line remembers its counterpart.
     *
     * @param Item[] $items
     */
    public function linkLineItems(string $sellerOrderId, array $items): void
    {
        try {
            $remote = $this->admin->getOrder($sellerOrderId, 'id,items.id,items.variant_sku,items.offer.id');
        } catch (\Throwable $e) {
            $this->logger->warning('Could not read Mercur seller order lines', ['order' => $sellerOrderId, 'exception' => $e]);
            return;
        }

        $remoteLines = [];
        foreach ($remote['items'] ?? [] as $line) {
            $remoteLines[] = [
                'id' => (string) $line['id'],
                'offer_id' => isset($line['offer']['id']) ? (string) $line['offer']['id'] : (string) ($line['offer_id'] ?? ''),
                'sku' => (string) ($line['variant_sku'] ?? ''),
            ];
        }
        $local = [];
        foreach ($items as $key => $item) {
            if ($item->getData('mercur_order_id') === $sellerOrderId) {
                $local[(string) $key] = ['offer_id' => (string) $item->getData('mercur_offer_id'), 'sku' => (string) $item->getSku()];
            }
        }
        foreach (LineMatcher::match($remoteLines, $local) as $key => $remoteId) {
            $items[$key]->setData('mercur_order_line_item_id', $remoteId);
        }
    }

    /**
     * @return Item[]
     */
    public function marketplaceItems(Order $order): array
    {
        return array_values(array_filter(
            $order->getAllVisibleItems(),
            static fn (Item $item) => (bool) $item->getData('mercur_offer_id')
        ));
    }

    /**
     * @return array<string, Item[]> Magento lines grouped by Mercur seller order id
     */
    public function itemsBySellerOrder(Order $order): array
    {
        $groups = [];
        foreach ($this->marketplaceItems($order) as $item) {
            $sellerOrder = (string) $item->getData('mercur_order_id');
            if ($sellerOrder !== '') {
                $groups[$sellerOrder][] = $item;
            }
        }

        return $groups;
    }

    /**
     * @return array<string, array{option_id: string, name: string, amount: float}>
     */
    private function shippingBySeller(Order $order, array $cart): array
    {
        $chosen = $order->getData('mercur_shipping_methods');
        $chosen = $chosen ? $this->json->unserialize((string) $chosen) : [];
        if (!is_array($chosen)) {
            return [];
        }

        $methods = [];
        foreach ($cart['shipping_methods'] ?? [] as $method) {
            $methods[(string) ($method['shipping_option_id'] ?? '')] = $method;
        }

        $result = [];
        foreach ($chosen as $sellerId => $optionId) {
            $optionId = is_array($optionId) ? (string) ($optionId['option_id'] ?? '') : (string) $optionId;
            $method = $methods[$optionId] ?? [];
            $result[(string) $sellerId] = [
                'option_id' => $optionId,
                'name' => (string) ($method['name'] ?? ''),
                'amount' => (float) ($method['amount'] ?? 0),
            ];
        }

        return $result;
    }
}
