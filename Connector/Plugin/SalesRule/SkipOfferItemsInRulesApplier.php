<?php

declare(strict_types=1);

namespace Mercur\Connector\Plugin\SalesRule;

use Magento\SalesRule\Model\RulesApplier;

/**
 * No rule is applied to a seller line, whatever its actions say.
 */
class SkipOfferItemsInRulesApplier
{
    /**
     * The last parameter is a string coupon code before 2.4.7 and an array of codes from then on.
     *
     * @return array<int|string, int|string>
     */
    public function aroundApplyRules(RulesApplier $subject, callable $proceed, $item, $rules, $skipValidation, $couponCodes = []): array
    {
        if (OfferItem::is($item)) {
            return [];
        }

        return (array) $proceed($item, $rules, $skipValidation, $couponCodes);
    }
}
