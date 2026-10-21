<?php

declare(strict_types=1);

namespace Mercur\Connector\Model\Total;

use Magento\Quote\Api\Data\ShippingAssignmentInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address\Total;
use Magento\Quote\Model\Quote\Address\Total\AbstractTotal;
use Mercur\Connector\Model\Checkout\ShadowCart;
use Mercur\Core\Model\Config;
use Psr\Log\LoggerInterface;

/**
 * Adds the per-seller shipping Mercur quotes for the marketplace lines. The
 * regular Magento carriers keep pricing the first-party lines.
 */
class MarketplaceShipping extends AbstractTotal
{
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

        if (!$this->config->isEnabled((int) $quote->getStoreId())
            || $address->getAddressType() !== 'shipping'
            || !$this->shadowCart->hasMarketplaceItems($quote)) {
            return $this;
        }

        try {
            $this->shadowCart->sync($quote);
            $this->shadowCart->syncAddress($quote, $address);
            $options = $this->shadowCart->shippingOptions($quote);
            $applied = $this->shadowCart->applyShippingMethods($quote, $options);
        } catch (\Throwable $e) {
            $this->logger->error('Mercur shipping quote failed', ['exception' => $e]);

            return $this;
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
}
