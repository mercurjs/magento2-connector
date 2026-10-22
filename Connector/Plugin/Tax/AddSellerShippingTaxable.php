<?php

declare(strict_types=1);

namespace Mercur\Connector\Plugin\Tax;

use Magento\Quote\Model\Quote\Address;
use Magento\Tax\Api\Data\QuoteDetailsItemInterfaceFactory;
use Magento\Tax\Api\Data\TaxClassKeyInterface;
use Magento\Tax\Api\Data\TaxClassKeyInterfaceFactory;
use Magento\Tax\Model\Config as TaxConfig;
use Magento\Tax\Model\Sales\Total\Quote\CommonTaxCollector;
use Magento\Tax\Model\Sales\Total\Quote\Tax;
use Mercur\Core\Model\Config;

/**
 * Hands the marketplace shipping to Magento's tax calculation as one extra
 * taxable of the quote, taxed with the store's shipping tax class.
 */
class AddSellerShippingTaxable
{
    public const TYPE = 'mercur_shipping';
    public const CODE = 'mercur_shipping';

    public function __construct(
        private readonly TaxConfig $taxConfig,
        private readonly Config $config,
        private readonly TaxClassKeyInterfaceFactory $taxClassKeys
    ) {
    }

    /**
     * @param array<int, \Magento\Tax\Api\Data\QuoteDetailsItemInterface> $result
     * @return array<int, \Magento\Tax\Api\Data\QuoteDetailsItemInterface>
     */
    public function afterMapQuoteExtraTaxables(
        Tax $subject,
        array $result,
        QuoteDetailsItemInterfaceFactory $itemDataObjectFactory,
        Address $address,
        $useBaseCurrency
    ): array {
        $store = $address->getQuote()->getStore();
        $base = (float) $address->getData('base_mercur_shipping_amount');
        if ($base <= 0 || !$this->config->isEnabled((int) $store->getId())) {
            return $result;
        }

        $result[] = $itemDataObjectFactory->create()
            ->setCode(self::CODE)
            ->setType(self::TYPE)
            ->setQuantity(1)
            ->setTaxClassKey(
                $this->taxClassKeys->create()
                    ->setType(TaxClassKeyInterface::TYPE_ID)
                    ->setValue((string) $this->taxConfig->getShippingTaxClass($store))
            )
            ->setUnitPrice($useBaseCurrency ? $base : (float) $address->getData('mercur_shipping_amount'))
            ->setIsTaxIncluded($this->config->shippingPricesIncludeTax((int) $store->getId()))
            ->setAssociatedItemCode(CommonTaxCollector::ASSOCIATION_ITEM_CODE_FOR_QUOTE);

        return $result;
    }
}
