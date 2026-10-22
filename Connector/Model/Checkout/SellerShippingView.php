<?php

declare(strict_types=1);

namespace Mercur\Connector\Model\Checkout;

use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item;
use Mercur\Connector\Model\Delivery\DeliveryEstimate;
use Mercur\Connector\Model\OfferRepository;
use Mercur\Connector\Model\ResourceModel\Seller\CollectionFactory as SellerCollectionFactory;
use Mercur\Connector\Model\SellerUrl;

/**
 * The marketplace part of a cart grouped by seller: its lines, the shipping
 * options Mercur offers for them, which one is applied, and when it arrives.
 * Shared by the cart page, checkout and GraphQL.
 */
class SellerShippingView
{
    public function __construct(
        private readonly ShadowCart $shadowCart,
        private readonly OfferRepository $offers,
        private readonly SellerCollectionFactory $sellers,
        private readonly SellerUrl $sellerUrl,
        private readonly DeliveryEstimate $delivery,
        private readonly PriceCurrencyInterface $priceCurrency
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function build(Quote $quote): array
    {
        $items = $this->shadowCart->marketplaceItems($quote);
        if ($items === []) {
            return [];
        }

        $storeId = (int) $quote->getStoreId();
        $offers = $this->offers->getByIds(
            array_map(static fn (Item $item) => (string) $item->getData('mercur_offer_id'), $items),
            (int) $quote->getStore()->getWebsiteId()
        );

        $groups = [];
        foreach ($items as $item) {
            $sellerId = (string) $item->getData('mercur_seller_id');
            $offer = $offers[(string) $item->getData('mercur_offer_id')] ?? null;
            $groups[$sellerId] ??= ['items' => [], 'leadtime' => null];
            $groups[$sellerId]['items'][] = [
                'item_id' => (int) $item->getId(),
                'name' => (string) $item->getName(),
                'sku' => (string) $item->getSku(),
                'qty' => (float) $item->getQty(),
            ];
            $leadtime = $offer?->getLeadtimeToShip();
            if ($leadtime !== null) {
                $groups[$sellerId]['leadtime'] = max((int) $groups[$sellerId]['leadtime'], $leadtime);
            }
        }

        $sellers = [];
        foreach ($this->sellers->create()->addFieldToFilter('seller_id', ['in' => array_keys($groups)]) as $seller) {
            $sellers[$seller->getSellerId()] = $seller;
        }

        $options = $this->safeShippingOptions($quote);
        $chosen = $this->shadowCart->chosenShippingMethods($quote);

        $result = [];
        foreach ($groups as $sellerId => $group) {
            $seller = $sellers[$sellerId] ?? null;
            $sellerOptions = $options[$sellerId] ?? [];
            $chosenId = $chosen[$sellerId] ?? $this->cheapestId($sellerOptions);

            $mapped = [];
            foreach ($sellerOptions as $option) {
                $amount = (float) ($option['amount'] ?? 0);
                $window = $this->delivery->estimate($group['leadtime'], $storeId, $option);
                $mapped[] = [
                    'id' => (string) $option['id'],
                    'name' => (string) ($option['name'] ?? ''),
                    'amount' => $amount,
                    'formatted_amount' => $this->priceCurrency->convertAndFormat($amount, false, PriceCurrencyInterface::DEFAULT_PRECISION, $quote->getStore()),
                    'chosen' => (string) $option['id'] === $chosenId,
                    'estimated_delivery' => $this->dates($window),
                    'estimated_delivery_label' => $this->delivery->format($window, $storeId),
                ];
            }

            $fallback = $this->delivery->estimate($group['leadtime'], $storeId);
            $result[] = [
                'seller_id' => $sellerId,
                'seller_name' => $seller ? $seller->getName() : $sellerId,
                'seller_handle' => $seller ? (string) $seller->getData('handle') : '',
                'seller_url' => $seller ? $this->sellerUrl->get((string) $seller->getData('handle')) : '',
                'items' => $group['items'],
                'options' => $mapped,
                'estimated_delivery' => $this->dates($fallback),
                'estimated_delivery_label' => $this->delivery->format($fallback, $storeId),
            ];
        }

        return $result;
    }

    private function safeShippingOptions(Quote $quote): array
    {
        try {
            return $this->shadowCart->shippingOptions($quote);
        } catch (\Throwable) {
            return [];
        }
    }

    private function cheapestId(array $options): ?string
    {
        if ($options === []) {
            return null;
        }
        usort($options, static fn ($a, $b) => ((float) ($a['amount'] ?? 0)) <=> ((float) ($b['amount'] ?? 0)));

        return (string) $options[0]['id'];
    }

    private function dates(?array $window): ?array
    {
        return $window === null ? null : [
            'from' => $window['from']->format('Y-m-d'),
            'to' => $window['to']->format('Y-m-d'),
        ];
    }
}
