<?php

declare(strict_types=1);

namespace Mercur\Catalog\Model\Product;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\Data\ProductInterfaceFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use Mercur\Catalog\Model\CatalogConfig;
use Mercur\Core\Model\Scope\WebsiteScope;

/**
 * Creates or refreshes Magento products from a published Mercur product: one
 * simple product per variant, grouped under a configurable parent when the
 * variant axes map to Magento attributes. Imported products hold no stock of
 * their own; they are in stock while a seller offers them.
 */
class Importer
{
    public function __construct(
        private readonly ProductRepositoryInterface $products,
        private readonly ProductInterfaceFactory $productFactory,
        private readonly CatalogConfig $config,
        private readonly AttributeWriter $attributes,
        private readonly ConfigurableAxes $axes,
        private readonly ConfigurableBuilder $configurable,
        private readonly WebsiteScope $websiteScope,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * @return string[] SKUs touched
     */
    public function import(array $mercurProduct): array
    {
        $axisHandles = $this->axes->fromProduct($mercurProduct);
        $axisCodes = $axisHandles === null ? [] : $this->configurable->usableAxes($axisHandles);
        $asChildren = $axisCodes !== [];
        $websiteIds = $this->websiteIds();

        $skus = [];
        $children = [];
        foreach ($mercurProduct['variants'] ?? [] as $variant) {
            $sku = (string) ($variant['sku'] ?? '');
            if ($sku === '') {
                continue;
            }
            $product = $this->find($sku);
            $isNew = $product === null;
            $product ??= $this->productFactory->create();
            if ($isNew) {
                $product->setSku($sku)
                    ->setTypeId(Type::TYPE_SIMPLE)
                    ->setAttributeSetId($this->config->getAttributeSetId())
                    ->setStatus($this->config->getImportedProductStatus())
                    ->setPrice(0)
                    ->setStockData([
                        'use_config_manage_stock' => 0,
                        'manage_stock' => 1,
                        'use_config_backorders' => 0,
                        'backorders' => 1,
                        'qty' => 0,
                        'is_in_stock' => 0,
                    ]);
            }
            $product->setWebsiteIds($websiteIds);
            $product->setVisibility($asChildren ? Visibility::VISIBILITY_NOT_VISIBLE : $this->config->getImportedProductVisibility());

            $title = (string) ($mercurProduct['title'] ?? $sku);
            $variantTitle = (string) ($variant['title'] ?? '');
            $product->setName($variantTitle && $variantTitle !== 'Default' ? "$title - $variantTitle" : $title);
            $product->setCustomAttribute('description', (string) ($mercurProduct['description'] ?? ''));
            $product->setCustomAttribute('mercur_product', 1);
            $product->setCustomAttribute('mercur_product_id', (string) $mercurProduct['id']);
            $product->setCustomAttribute('mercur_variant_id', (string) $variant['id']);
            if (!empty($mercurProduct['thumbnail']) && $isNew) {
                $product->setCustomAttribute('mercur_thumbnail', (string) $mercurProduct['thumbnail']);
            }
            $this->attributes->apply($product, $mercurProduct['attributes'] ?? [], $variant);
            $children[] = $this->products->save($product);
            $skus[] = $sku;
        }

        if ($asChildren) {
            $parentSku = $this->configurable->build($mercurProduct, $children, $axisCodes, $websiteIds);
            if ($parentSku !== null) {
                $skus[] = $parentSku;
            }
        }

        return $skus;
    }

    /**
     * Imported products go to the websites in the catalog settings, or else
     * to every website that sells marketplace offers.
     *
     * @return int[]
     */
    private function websiteIds(): array
    {
        $configured = $this->config->getWebsiteIds();
        if ($configured !== []) {
            return $configured;
        }
        $selling = array_keys($this->websiteScope->all());
        if ($selling !== []) {
            return $selling;
        }

        return array_map(static fn ($website) => (int) $website->getId(), $this->storeManager->getWebsites());
    }

    private function find(string $sku): ?ProductInterface
    {
        try {
            return $this->products->get($sku, true);
        } catch (NoSuchEntityException) {
            return null;
        }
    }
}
