<?php

declare(strict_types=1);

namespace Mercur\Connector\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;
use Mercur\Connector\Model\Carrier\Marketplace;
use Mercur\Connector\Model\Order\MarketplaceFields;
use Mercur\Connector\Model\Order\ShippingDescription;
use Mercur\Connector\Model\ResourceModel\Seller\CollectionFactory as SellerCollectionFactory;

/**
 * Runs on sales_model_service_quote_submit_before, after Magento has built
 * the order from the quote and before it is saved.
 */
class CopyMarketplaceFieldsToOrder implements ObserverInterface
{
    public function __construct(private readonly SellerCollectionFactory $sellers)
    {
    }

    public function execute(Observer $observer): void
    {
        $order = $observer->getData('order');
        $quote = $observer->getData('quote');
        if (!$order instanceof Order || !$quote instanceof Quote) {
            return;
        }

        MarketplaceFields::copy($quote, $order, MarketplaceFields::ORDER);
        $address = $quote->getIsVirtual() ? $quote->getBillingAddress() : $quote->getShippingAddress();
        if ($address) {
            MarketplaceFields::copy($address, $order, MarketplaceFields::ADDRESS);
        }

        $quoteItems = [];
        foreach ($quote->getAllItems() as $item) {
            $quoteItems[(int) $item->getId()] = $item;
        }
        foreach ($order->getAllItems() as $orderItem) {
            $quoteItem = $quoteItems[(int) $orderItem->getQuoteItemId()] ?? null;
            if ($quoteItem !== null) {
                MarketplaceFields::copy($quoteItem, $orderItem, MarketplaceFields::ITEM);
            }
        }

        $this->describeShipping($order);
    }

    private function describeShipping(Order $order): void
    {
        $chosen = json_decode((string) $order->getData('mercur_shipping_methods'), true);
        if (!is_array($chosen) || $chosen === []) {
            return;
        }

        $names = [];
        foreach ($this->sellers->create()->addFieldToFilter('seller_id', ['in' => array_keys($chosen)]) as $seller) {
            $names[$seller->getSellerId()] = $seller->getName();
        }

        $placeholder = $order->getShippingMethod() === Marketplace::CODE . '_' . Marketplace::CODE;
        $description = ShippingDescription::compose(
            $chosen,
            $names,
            $placeholder ? null : (string) $order->getShippingDescription()
        );
        if ($description !== null) {
            $order->setShippingDescription($description);
        }
    }
}
