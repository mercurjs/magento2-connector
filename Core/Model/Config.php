<?php

declare(strict_types=1);

namespace Mercur\Core\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;

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

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly EncryptorInterface $encryptor
    ) {
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

    public function getTimeout(): int
    {
        return (int) $this->scopeConfig->getValue(self::PATH_TIMEOUT) ?: 15;
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

    public function getPageSize(): int
    {
        return max(1, (int) $this->scopeConfig->getValue(self::PATH_PAGE_SIZE) ?: 100);
    }

    private function decrypt(string $path, ?int $storeId = null): string
    {
        $value = (string) $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE, $storeId);

        return $value === '' ? '' : $this->encryptor->decrypt($value);
    }
}
