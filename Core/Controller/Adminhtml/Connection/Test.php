<?php

declare(strict_types=1);

namespace Mercur\Core\Controller\Adminhtml\Connection;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Store\Model\StoreManagerInterface;
use Mercur\Core\Model\Api\AdminApi;
use Mercur\Core\Model\Api\ApiException;
use Mercur\Core\Model\Config;

/**
 * Checks the saved credentials of the scope being edited: the status endpoint
 * proves the admin key, the region lookup proves the region id.
 */
class Test extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Mercur_Core::config';

    public function __construct(
        Action\Context $context,
        private readonly JsonFactory $jsonFactory,
        private readonly AdminApi $api,
        private readonly Config $config,
        private readonly StoreManagerInterface $storeManager
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        $result = $this->jsonFactory->create();
        $storeId = $this->resolveStoreId();

        if ($this->config->getApiUrl($storeId) === '' || $this->config->getApiKey($storeId) === '') {
            return $result->setData(['success' => false, 'message' => (string) __('Save the API URL and admin API key first.')]);
        }

        try {
            $status = $this->api->status($storeId);
            $parts = [(string) __('Connected to %1.', $this->config->getApiUrl($storeId))];
            if (!empty($status['version'])) {
                $parts[] = (string) __('Plugin version %1.', $status['version']);
            }

            $regionId = $this->config->getRegionId($storeId);
            if ($regionId !== '') {
                $region = $this->api->getRegion($regionId, $storeId);
                $parts[] = $region === []
                    ? (string) __('Region %1 was not found.', $regionId)
                    : (string) __('Region %1 (%2).', $region['name'] ?? $regionId, strtoupper((string) ($region['currency_code'] ?? '')));
            } else {
                $parts[] = (string) __('No region id set for this scope.');
            }

            return $result->setData(['success' => true, 'message' => implode(' ', $parts)]);
        } catch (ApiException $e) {
            $message = $e->getStatus() === 401 || $e->getStatus() === 403
                ? (string) __('Mercur rejected the admin API key (HTTP %1).', $e->getStatus())
                : (string) __('Mercur answered with an error: %1', $e->getMessage());

            return $result->setData(['success' => false, 'message' => $message]);
        } catch (\Throwable $e) {
            return $result->setData(['success' => false, 'message' => (string) __('Connection failed: %1', $e->getMessage())]);
        }
    }

    private function resolveStoreId(): ?int
    {
        $store = $this->getRequest()->getParam('store');
        if ($store) {
            return (int) $store;
        }
        $website = $this->getRequest()->getParam('website');
        if ($website) {
            $defaultStore = $this->storeManager->getWebsite((int) $website)->getDefaultStore();

            return $defaultStore ? (int) $defaultStore->getId() : null;
        }

        return null;
    }
}
