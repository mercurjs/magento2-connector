<?php

declare(strict_types=1);

namespace Mercur\Core\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class Weekday implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => '1', 'label' => __('Monday')],
            ['value' => '2', 'label' => __('Tuesday')],
            ['value' => '3', 'label' => __('Wednesday')],
            ['value' => '4', 'label' => __('Thursday')],
            ['value' => '5', 'label' => __('Friday')],
            ['value' => '6', 'label' => __('Saturday')],
            ['value' => '7', 'label' => __('Sunday')],
        ];
    }
}
