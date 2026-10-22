<?php

declare(strict_types=1);

namespace Mercur\Connector\Model\Order;

use Magento\Framework\Serialize\Serializer\Json;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Item;
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
        private readonly Json $json,
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
        $order->setData('mercur_shipping_methods', $this->json->serialize($this->shippingBySeller($order, $cart)));
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

        foreach (array_unique($bySeller) as $sellerOrderId) {
            $this->linkLineItems($sellerOrderId, $items);
        }

        $this->orders->save($order);
    }

    /**
     * Seller order lines are new records in Mercur; fulfillments point at
     * them, so each Magento line remembers its counterpart. Lines are matched
     * on SKU, which is unique within one seller's order.
     *
     * @param Item[] $items
     */
    public function linkLineItems(string $sellerOrderId, array $items): void
    {
        try {
            $remote = $this->admin->getOrder($sellerOrderId, 'id,items.id,items.variant_sku');
        } catch (\Throwable $e) {
            $this->logger->warning('Could not read Mercur seller order lines', ['order' => $sellerOrderId, 'exception' => $e]);
            return;
        }

        $bySku = [];
        foreach ($remote['items'] ?? [] as $line) {
            $bySku[(string) ($line['variant_sku'] ?? '')] = (string) $line['id'];
        }
        foreach ($items as $item) {
            if ($item->getData('mercur_order_id') === $sellerOrderId && isset($bySku[(string) $item->getSku()])) {
                $item->setData('mercur_order_line_item_id', $bySku[(string) $item->getSku()]);
            }
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
