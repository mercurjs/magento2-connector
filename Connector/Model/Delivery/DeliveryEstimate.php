<?php

declare(strict_types=1);

namespace Mercur\Connector\Model\Delivery;

use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Mercur\Core\Model\Config;

/**
 * Applies the website's delivery settings to the estimator. A shipping option
 * may carry its own transit days in `data.transit_days_min` / `data.transit_days_max`.
 */
class DeliveryEstimate
{
    public function __construct(
        private readonly Estimator $estimator,
        private readonly Config $config,
        private readonly TimezoneInterface $timezone
    ) {
    }

    /**
     * @return array{from: \DateTimeImmutable, to: \DateTimeImmutable}|null
     */
    public function estimate(?int $leadtimeDays, int $storeId, ?array $shippingOption = null): ?array
    {
        if (!$this->config->isDeliveryEstimateEnabled($storeId)) {
            return null;
        }

        $data = is_array($shippingOption['data'] ?? null) ? $shippingOption['data'] : [];
        $min = isset($data['transit_days_min']) ? (int) $data['transit_days_min'] : $this->config->getTransitDaysMin($storeId);
        $max = isset($data['transit_days_max']) ? (int) $data['transit_days_max'] : $this->config->getTransitDaysMax($storeId);

        $now = \DateTimeImmutable::createFromInterface($this->timezone->scopeDate($storeId, null, true));

        return $this->estimator->estimate(
            $now,
            $leadtimeDays,
            $min,
            $max,
            $this->config->getCutoffTime($storeId),
            $this->config->getBusinessDays($storeId)
        );
    }

    /**
     * @return array{from: string, to: string}|null ISO dates
     */
    public function estimateDates(?int $leadtimeDays, int $storeId, ?array $shippingOption = null): ?array
    {
        $window = $this->estimate($leadtimeDays, $storeId, $shippingOption);

        return $window === null ? null : [
            'from' => $window['from']->format('Y-m-d'),
            'to' => $window['to']->format('Y-m-d'),
        ];
    }

    public function format(?array $window, int $storeId): ?string
    {
        if ($window === null) {
            return null;
        }
        $from = $this->timezone->formatDate($window['from'], \IntlDateFormatter::MEDIUM);
        $to = $this->timezone->formatDate($window['to'], \IntlDateFormatter::MEDIUM);

        return $from === $to ? (string) __('Arrives %1', $from) : (string) __('Arrives %1 – %2', $from, $to);
    }
}
