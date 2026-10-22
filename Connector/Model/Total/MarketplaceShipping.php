<?php

declare(strict_types=1);

namespace Mercur\Connector\Model\Total;

use Magento\Quote\Api\Data\ShippingAssignmentInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Model\Quote\Address\Total;
use Magento\Quote\Model\Quote\Address\Total\AbstractTotal;
use Mercur\Connector\Model\Checkout\ShadowCart;
use Mercur\Connector\Model\Checkout\ShippingErrorGate;
use Mercur\Connector\Model\Checkout\SyncHash;
use Mercur\Core\Model\Config;
use Psr\Log\LoggerInterface;

/**
 * Adds the per-seller shipping Mercur quotes for the marketplace lines. The
 * regular Magento carriers keep pricing the first-party lines. A quote that
 * cannot be priced leaves an error on the address that blocks checkout.
 */
class MarketplaceShipping extends AbstractTotal
{
    public const ERROR_ORIGIN = 'mercur';

    public function __construct(
        private readonly ShadowCart $shadowCart,
        private readonly Config $config,
        private readonly LoggerInterface $logger
    ) {
        $this->setCode('mercur_shipping');
    }

    public function collect(Quote $quote, ShippingAssignmentInterface $shippingAssignment, Total $total): self
    {
        parent::collect($quote, $shippingAssignment, $total);

        $address = $shippingAssignment->getShipping()->getAddress();
        $address->setData('mercur_shipping_amount', 0);
        $address->setData('base_mercur_shipping_amount', 0);
        $address->setData('mercur_shipping_error', null);

        if (!$this->config->isEnabled((int) $quote->getStoreId())
            || $address->getAddressType() !== 'shipping'
            || !$this->shadowCart->hasMarketplaceItems($quote)) {
            return $this;
        }

        try {
            $applied = $this->quoteSellers($quote, $address);
        } catch (\Throwable $e) {
            $this->logger->error('Mercur shipping quote failed', ['exception' => $e]);
            $this->fail($quote, $address);

            return $this;
        }

        foreach ($this->sellerIds($quote) as $sellerId) {
            if (!isset($applied[$sellerId])) {
                $this->fail($quote, $address);

                return $this;
            }
        }

        $base = 0.0;
        foreach ($applied as $option) {
            $base += (float) ($option['amount'] ?? 0);
        }
        $amount = $quote->getStore()->getBaseCurrency()->convert($base, $quote->getQuoteCurrencyCode());

        $address->setData('mercur_shipping_amount', $amount);
        $address->setData('base_mercur_shipping_amount', $base);
        $total->setTotalAmount($this->getCode(), $amount);
        $total->setBaseTotalAmount($this->getCode(), $base);

        return $this;
    }

    public function fetch(Quote $quote, Total $total): array
    {
        $amount = (float) $total->getTotalAmount($this->getCode());
        if ($amount <= 0) {
            return [];
        }

        return [
            'code' => $this->getCode(),
            'title' => __('Marketplace shipping'),
            'value' => $amount,
        ];
    }

    /**
     * @return array<string, array> applied option per seller id
     */
    private function quoteSellers(Quote $quote, Address $address): array
    {
        $lines = [];
        foreach ($this->shadowCart->marketplaceItems($quote) as $item) {
            $lines[(string) $item->getData('mercur_offer_id')] = (int) $item->getQty();
        }
        $fingerprint = $this->shadowCart->addressFingerprint($address);
        $hash = SyncHash::build($lines, $fingerprint, $this->shadowCart->chosenShippingMethods($quote));

        if ((string) $quote->getData('mercur_cart_id') !== '' && $quote->getData('mercur_sync_hash') === $hash) {
            $cached = $this->shadowCart->appliedShippingMethods($quote);
            if ($cached !== null) {
                return $cached;
            }
        }

        $cart = $this->shadowCart->sync($quote) ?? [];
        $this->shadowCart->syncAddress($quote, $address);
        $options = $this->shadowCart->shippingOptions($quote);
        $current = array_map(
            static fn ($method) => (string) ($method['shipping_option_id'] ?? ''),
            $cart['shipping_methods'] ?? []
        );
        $applied = $this->shadowCart->applyShippingMethods($quote, $options, $current);
        $quote->setData('mercur_sync_hash', SyncHash::build($lines, $fingerprint, $this->shadowCart->chosenShippingMethods($quote)));

        return $applied;
    }

    /**
     * @return string[]
     */
    private function sellerIds(Quote $quote): array
    {
        $ids = [];
        foreach ($this->shadowCart->marketplaceItems($quote) as $item) {
            $ids[(string) $item->getData('mercur_seller_id')] = true;
        }

        return array_keys($ids);
    }

    private function fail(Quote $quote, Address $address): void
    {
        $message = (string) __('Marketplace shipping could not be quoted. Please try again.');
        $address->setData('mercur_shipping_error', $message);
        $quote->setData('mercur_sync_hash', null);
        $quote->addErrorInfo('error', self::ERROR_ORIGIN, ShippingErrorGate::ERROR_CODE, $message);
    }
}
