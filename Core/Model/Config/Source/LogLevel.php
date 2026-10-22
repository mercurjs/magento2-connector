<?php

declare(strict_types=1);

namespace Mercur\Core\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class LogLevel implements OptionSourceInterface
{
    public const OFF = 'off';
    public const ERRORS = 'errors';
    public const REQUESTS = 'requests';
    public const REQUESTS_AND_BODIES = 'requests_and_bodies';

    public function toOptionArray(): array
    {
        return [
            ['value' => self::OFF, 'label' => __('Off')],
            ['value' => self::ERRORS, 'label' => __('Errors only')],
            ['value' => self::REQUESTS, 'label' => __('Every request')],
            ['value' => self::REQUESTS_AND_BODIES, 'label' => __('Every request with bodies')],
        ];
    }
}
