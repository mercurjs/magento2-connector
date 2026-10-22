<?php

declare(strict_types=1);

namespace Mercur\Core\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class Config
{
    private const PATH_ENABLED = 'mercur/general/enabled';
    private const PATH_API_URL = 'mercur/general/api_url';
    private const PATH_API_KEY = 'mercur/general/api_key';
    private const PATH_PUBLISHABLE_KEY = 'mercur/general/publishable_key';
    private const PATH_SALES_CHANNEL_ID = 'mercur/general/sales_channel_id';
    private const PATH_REGION_ID = 'mercur/general/region_id';
    private const PATH_CHANNEL = 'mercur/general/channel';
    private const PATH_TIMEOUT = 'mercur/general/timeout';
    private const PATH_BEST_OFFER_RULE = 'mercur/offers/best_offer_rule';
    private const PATH_OPERATOR_LABEL = 'mercur/offers/operator_label';
    private const PATH_WEBHOOK_SECRET = 'mercur/webhook/secret';
    private const PATH_PAGE_SIZE = 'mercur/sync/page_size';
    private const PATH_DELIVERY_ENABLED = 'mercur/delivery/enabled';
    private const PATH_CUTOFF_TIME = 'mercur/delivery/cutoff_time';
    private const PATH_BUSINESS_DAYS = 'mercur/delivery/business_days';
    private const PATH_TRANSIT_MIN = 'mercur/delivery/transit_days_min';
    private const PATH_TRANSIT_MAX = 'mercur/delivery/transit_days_max';
    private const PATH_SHIPPING_PRICES_INCLUDE_TAX = 'mercur/checkout/shipping_prices_include_tax';
    private const PATH_AUTO_REMOVE_UNAVAILABLE = 'mercur/checkout/auto_remove_unavailable';
    private const PATH_AUTO_CREATE_STATUSES = 'mercur/order/auto_create_statuses';
    private const PATH_RETRIES = 'mercur/general/retries';
    private const PATH_RETRY_DELAY_MS = 'mercur/general/retry_delay_ms';
    private const PATH_LOG_LEVEL = 'mercur/general/log_level';
    private const PATH_WEBHOOK_MAX_AGE_SEC = 'mercur/webhook/max_age_sec';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly EncryptorInterface $encryptor,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * True when any website has the connector switched on; crons that mirror
     * the marketplace run as long as one storefront sells offers.
     */
    public function isEnabledAnywhere(): bool
    {
        if ($this->isEnabled()) {
            return true;
        }
        foreach ($this->storeManager->getWebsites() as $website) {
            $store = $website->getDefaultStore();
            if ($store && $this->isEnabled((int) $store->getId())) {
                return true;
            }
        }

        return false;
    }

    public function isEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::PATH_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getApiUrl(?int $storeId = null): string
    {
        return rtrim((string) $this->scopeConfig->getValue(self::PATH_API_URL, ScopeInterface::SCOPE_STORE, $storeId), '/');
    }

    public function getApiKey(?int $storeId = null): string
    {
        return $this->decrypt(self::PATH_API_KEY, $storeId);
    }

    public function getPublishableKey(?int $storeId = null): string
    {
        return $this->decrypt(self::PATH_PUBLISHABLE_KEY, $storeId);
    }

    public function getSalesChannelId(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(self::PATH_SALES_CHANNEL_ID, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getRegionId(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(self::PATH_REGION_ID, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getChannel(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(self::PATH_CHANNEL, ScopeInterface::SCOPE_STORE, $storeId) ?: 'magento';
    }

    public function getTimeout(?int $storeId = null): int
    {
        return (int) $this->scopeConfig->getValue(self::PATH_TIMEOUT, ScopeInterface::SCOPE_STORE, $storeId) ?: 15;
    }

    public function getRetries(?int $storeId = null): int
    {
        $value = $this->scopeConfig->getValue(self::PATH_RETRIES, ScopeInterface::SCOPE_STORE, $storeId);

        return $value === null || $value === '' ? 2 : max(0, (int) $value);
    }

    public function getRetryDelayMs(?int $storeId = null): int
    {
        $value = $this->scopeConfig->getValue(self::PATH_RETRY_DELAY_MS, ScopeInterface::SCOPE_STORE, $storeId);

        return $value === null || $value === '' ? 500 : max(0, (int) $value);
    }

    public function getLogLevel(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(self::PATH_LOG_LEVEL, ScopeInterface::SCOPE_STORE, $storeId) ?: 'errors';
    }

    /**
     * @return int 0 when webhooks are accepted regardless of their age
     */
    public function getWebhookMaxAgeSec(): int
    {
        return max(0, (int) $this->scopeConfig->getValue(self::PATH_WEBHOOK_MAX_AGE_SEC));
    }

    public function getBestOfferRule(?int $websiteId = null): string
    {
        return (string) $this->scopeConfig->getValue(self::PATH_BEST_OFFER_RULE, ScopeInterface::SCOPE_WEBSITE, $websiteId);
    }

    public function getOperatorLabel(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(self::PATH_OPERATOR_LABEL, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getWebhookSecret(): string
    {
        return $this->decrypt(self::PATH_WEBHOOK_SECRET);
    }

    public function getPageSize(?int $storeId = null): int
    {
        return max(1, (int) $this->scopeConfig->getValue(self::PATH_PAGE_SIZE, ScopeInterface::SCOPE_STORE, $storeId) ?: 100);
    }

    public function isDeliveryEstimateEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::PATH_DELIVERY_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getCutoffTime(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(self::PATH_CUTOFF_TIME, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @return int[] ISO weekday numbers, 1 = Monday
     */
    public function getBusinessDays(?int $storeId = null): array
    {
        $raw = (string) $this->scopeConfig->getValue(self::PATH_BUSINESS_DAYS, ScopeInterface::SCOPE_STORE, $storeId);

        return array_values(array_filter(array_map('intval', explode(',', $raw))));
    }

    public function getTransitDaysMin(?int $storeId = null): int
    {
        return (int) $this->scopeConfig->getValue(self::PATH_TRANSIT_MIN, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getTransitDaysMax(?int $storeId = null): int
    {
        return (int) $this->scopeConfig->getValue(self::PATH_TRANSIT_MAX, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function shippingPricesIncludeTax(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::PATH_SHIPPING_PRICES_INCLUDE_TAX, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function autoRemoveUnavailableItems(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::PATH_AUTO_REMOVE_UNAVAILABLE, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @return string[] Magento order statuses that create the order in Mercur
     */
    public function getAutoCreateStatuses(?int $storeId = null): array
    {
        $raw = (string) $this->scopeConfig->getValue(self::PATH_AUTO_CREATE_STATUSES, ScopeInterface::SCOPE_STORE, $storeId);

        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }

    private function decrypt(string $path, ?int $storeId = null): string
    {
        $value = (string) $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE, $storeId);

        return $value === '' ? '' : $this->encryptor->decrypt($value);
    }
}
