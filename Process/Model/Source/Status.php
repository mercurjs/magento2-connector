<?php

declare(strict_types=1);

namespace Mercur\Process\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Mercur\Process\Model\Process;

class Status implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        $options = [];
        foreach (self::labels() as $value => $label) {
            $options[] = ['value' => $value, 'label' => $label];
        }

        return $options;
    }

    /**
     * @return array<string, \Magento\Framework\Phrase>
     */
    public static function labels(): array
    {
        return [
            Process::STATUS_PENDING => __('Pending'),
            Process::STATUS_PENDING_RETRY => __('Pending retry'),
            Process::STATUS_PROCESSING => __('Processing'),
            Process::STATUS_COMPLETED => __('Completed'),
            Process::STATUS_ERROR => __('Error'),
            Process::STATUS_TIMEOUT => __('Timed out'),
            Process::STATUS_STOPPED => __('Stopped'),
        ];
    }
}
