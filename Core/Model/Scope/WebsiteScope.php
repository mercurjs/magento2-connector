<?php

declare(strict_types=1);

namespace Mercur\Core\Model\Scope;

use Magento\Store\Model\StoreManagerInterface;
use Mercur\Core\Model\Config;

/**
 * The websites that sell marketplace offers: connector enabled and a Mercur
 * region set at website scope. Each one is priced from its own region.
 */
class WebsiteScope
{
    public function __construct(
        private readonly StoreManagerInterface $storeManager,
        private readonly Config $config
    ) {
    }

    /**
     * @return array<int, array{store_id:int, region_id:string, currency_code:string}> keyed by website id
     */
    public function all(): array
    {
        $result = [];
        foreach ($this->storeManager->getWebsites() as $website) {
            $store = $website->getDefaultStore();
            if (!$store) {
                continue;
            }
            $storeId = (int) $store->getId();
            $regionId = $this->config->getRegionId($storeId);
            if (!$this->config->isEnabled($storeId) || $regionId === '') {
                continue;
            }
            $result[(int) $website->getId()] = [
                'store_id' => $storeId,
                'region_id' => $regionId,
                'currency_code' => strtoupper((string) $website->getBaseCurrencyCode()),
            ];
        }

        return $result;
    }

    public function currentWebsiteId(): int
    {
        return (int) $this->storeManager->getStore()->getWebsiteId();
    }

    public function websiteIdForStore(int $storeId): int
    {
        return (int) $this->storeManager->getStore($storeId)->getWebsiteId();
    }
}
