<?php

declare(strict_types=1);

namespace Mercur\Core\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class BestOfferRule implements OptionSourceInterface
{
    public const LOWEST_TOTAL_PRICE = 'lowest_total_price';
    public const OPERATOR_FIRST = 'operator_first';
    public const PREMIUM_FIRST = 'premium_first';

    public function toOptionArray(): array
    {
        return [
            ['value' => self::LOWEST_TOTAL_PRICE, 'label' => __('Lowest total price')],
            ['value' => self::OPERATOR_FIRST, 'label' => __('Our own stock first')],
            ['value' => self::PREMIUM_FIRST, 'label' => __('Premium sellers first')],
        ];
    }
}
