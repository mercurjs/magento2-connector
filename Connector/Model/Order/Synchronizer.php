<?php

declare(strict_types=1);

namespace Mercur\Connector\Model\Order;

use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Item;
use Mercur\Connector\Model\Checkout\ShadowCart;
use Mercur\Core\Model\Api\AdminApi;
use Mercur\Core\Model\Api\StoreApi;
use Mercur\Core\Model\Config;
use Psr\Log\LoggerInterface;

/**
 * After Magento captured the payment, records it on the shadow cart's external
 * payment session and completes the cart. Mercur splits it into one order per
 * seller; their ids are written back onto the Magento order and its items.
 */
class Synchronizer
{
    public function __construct(
        private readonly StoreApi $store,
        private readonly AdminApi $admin,
        private readonly Config $config,
        private readonly OrderRepositoryInterface $orders,
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
        $cart = $this->store->getCart($cartId, $storeId);

        $collection = $this->store->createPaymentCollection($cartId, $storeId);
        $this->store->createExternalPaymentSession((string) $collection['id'], [
            'reference' => (string) $order->getIncrementId(),
            'amount' => (float) ($cart['total'] ?? 0),
            'currency_code' => strtolower((string) $order->getBaseCurrencyCode()),
            'captured_at' => gmdate(DATE_ATOM),
            'channel' => $this->config->getChannel($storeId),
            'payment_method' => (string) $order->getPayment()?->getMethod(),
        ], $storeId);

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
        $order->addCommentToStatusHistory(__('Marketplace order group %1 created in Mercur.', $group['display_id'] ?? $group['id']));

        $bySeller = [];
        foreach ($group['orders'] ?? [] as $child) {
            $sellerId = $child['seller_id'] ?? ($child['seller']['id'] ?? null);
            if ($sellerId) {
                $bySeller[(string) $sellerId] = (string) $child['id'];
            }
        }
        foreach ($items as $item) {
            $sellerId = (string) $item->getData('mercur_seller_id');
            if (isset($bySeller[$sellerId])) {
                $item->setData('mercur_order_id', $bySeller[$sellerId]);
                $item->setData('mercur_status', 'not_fulfilled');
            }
        }

        $this->orders->save($order);
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
     * @return array<string, Item[]> items grouped by Mercur seller order id
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
}
