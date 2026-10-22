<?php

declare(strict_types=1);

namespace Mercur\Catalog\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;

class CatalogConfig
{
    public function __construct(private readonly ScopeConfigInterface $scopeConfig)
    {
    }

    public function getAttributeGroup(): string
    {
        return (string) $this->scopeConfig->getValue('mercur/catalog/attribute_group') ?: 'Marketplace';
    }

    public function getAttributeSetId(): int
    {
        return (int) $this->scopeConfig->getValue('mercur/catalog/attribute_set_id') ?: 4;
    }

    public function importProducts(): bool
    {
        return $this->scopeConfig->isSetFlag('mercur/catalog/import_products');
    }

    public function getImportedProductStatus(): int
    {
        return (int) $this->scopeConfig->getValue('mercur/catalog/imported_product_status') ?: 1;
    }

    /**
     * @return int[]
     */
    public function getWebsiteIds(): array
    {
        $raw = (string) $this->scopeConfig->getValue('mercur/catalog/website_ids');

        return array_values(array_filter(array_map('intval', explode(',', $raw))));
    }

    public function getImportedProductVisibility(): int
    {
        return (int) $this->scopeConfig->getValue('mercur/catalog/imported_product_visibility') ?: 4;
    }
}
