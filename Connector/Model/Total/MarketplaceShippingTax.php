<?php

declare(strict_types=1);

namespace Mercur\Connector\Model\Total;

use Magento\Quote\Api\Data\ShippingAssignmentInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address\Total;
use Magento\Quote\Model\Quote\Address\Total\AbstractTotal;
use Magento\Tax\Model\Sales\Total\Quote\CommonTaxCollector;
use Mercur\Connector\Plugin\Tax\AddSellerShippingTaxable;

/**
 * Records the tax Magento computed on the marketplace shipping. Runs after
 * the tax total, which has already put this tax into tax_amount and the
 * applied taxes, so nothing is added to the grand total here. When seller
 * prices include tax the shipping total is brought down to its net amount
 * so the gross is not counted twice.
 */
class MarketplaceShippingTax extends AbstractTotal
{
    private const SHIPPING_CODE = 'mercur_shipping';

    public function __construct()
    {
        $this->setCode('mercur_shipping_tax');
    }

    public function collect(Quote $quote, ShippingAssignmentInterface $shippingAssignment, Total $total): self
    {
        parent::collect($quote, $shippingAssignment, $total);

        $address = $shippingAssignment->getShipping()->getAddress();
        foreach (['mercur_shipping_tax_amount', 'base_mercur_shipping_tax_amount'] as $field) {
            $address->setData($field, 0);
        }
        $address->setData('mercur_shipping_incl_tax', (float) $address->getData('mercur_shipping_amount'));
        $address->setData('base_mercur_shipping_incl_tax', (float) $address->getData('base_mercur_shipping_amount'));

        $details = $this->details($total);
        if ($details === null) {
            return $this;
        }

        $excl = (float) ($details[CommonTaxCollector::KEY_TAX_DETAILS_ROW_TOTAL] ?? 0);
        $baseExcl = (float) ($details[CommonTaxCollector::KEY_TAX_DETAILS_BASE_ROW_TOTAL] ?? 0);
        $tax = (float) ($details[CommonTaxCollector::KEY_TAX_DETAILS_ROW_TAX] ?? 0);
        $baseTax = (float) ($details[CommonTaxCollector::KEY_TAX_DETAILS_BASE_ROW_TAX] ?? 0);

        $address->setData('mercur_shipping_amount', $excl);
        $address->setData('base_mercur_shipping_amount', $baseExcl);
        $address->setData('mercur_shipping_tax_amount', $tax);
        $address->setData('base_mercur_shipping_tax_amount', $baseTax);
        $address->setData('mercur_shipping_incl_tax', (float) ($details[CommonTaxCollector::KEY_TAX_DETAILS_ROW_TOTAL_INCL_TAX] ?? $excl + $tax));
        $address->setData('base_mercur_shipping_incl_tax', (float) ($details[CommonTaxCollector::KEY_TAX_DETAILS_BASE_ROW_TOTAL_INCL_TAX] ?? $baseExcl + $baseTax));
        $total->setTotalAmount(self::SHIPPING_CODE, $excl);
        $total->setBaseTotalAmount(self::SHIPPING_CODE, $baseExcl);

        return $this;
    }

    public function fetch(Quote $quote, Total $total): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function details(Total $total): ?array
    {
        $extra = $total->getData('extra_taxable_details');
        $rows = $extra[AddSellerShippingTaxable::TYPE][CommonTaxCollector::ASSOCIATION_ITEM_CODE_FOR_QUOTE] ?? [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            if (($row[CommonTaxCollector::KEY_TAX_DETAILS_CODE] ?? '') === AddSellerShippingTaxable::CODE) {
                return $row;
            }
        }

        return null;
    }
}
