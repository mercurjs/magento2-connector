<?php

declare(strict_types=1);

namespace Mercur\Connector\Plugin\SalesRule;

use Magento\Framework\Model\AbstractModel;
use Magento\Quote\Model\Quote\Address as QuoteAddress;
use Magento\SalesRule\Model\Rule\Condition\Address;
use Mercur\Connector\Model\SalesRule\OperatorSubtotal;

/**
 * Subtotal, quantity and weight conditions are checked against the operator's
 * lines only; the seller lines cannot help a cart reach a rule's threshold.
 */
class OperatorOnlyAddressCondition
{
    public function aroundValidate(Address $subject, callable $proceed, AbstractModel $model): bool
    {
        $address = $model instanceof QuoteAddress ? $model : $this->addressOf($model);
        if ($address === null) {
            return (bool) $proceed($model);
        }

        $rows = [];
        $hasOffer = false;
        foreach ($address->getAllItems() as $item) {
            $offer = OfferItem::is($item);
            $hasOffer = $hasOffer || $offer;
            $rows[] = [
                'offer' => $offer,
                'parent' => (bool) $item->getParentItem(),
                'row_total' => (float) $item->getRowTotal(),
                'base_row_total' => (float) $item->getBaseRowTotal(),
                'discount_amount' => (float) $item->getDiscountAmount(),
                'base_discount_amount' => (float) $item->getBaseDiscountAmount(),
                'row_total_incl_tax' => (float) $item->getRowTotalInclTax(),
                'base_row_total_incl_tax' => (float) $item->getBaseRowTotalInclTax(),
                'qty' => (float) $item->getQty(),
                'row_weight' => (float) $item->getRowWeight(),
            ];
        }
        if (!$hasOffer) {
            return (bool) $proceed($model);
        }

        $operator = clone $address;
        $operator->addData(OperatorSubtotal::sum($rows));

        return (bool) $proceed($operator);
    }

    private function addressOf(AbstractModel $model): ?QuoteAddress
    {
        $quote = method_exists($model, 'getQuote') ? $model->getQuote() : null;
        if (!$quote) {
            return null;
        }

        return $quote->isVirtual() ? $quote->getBillingAddress() : $quote->getShippingAddress();
    }
}
