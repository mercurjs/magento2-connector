<?php

declare(strict_types=1);

namespace Mercur\Catalog\Model\Product;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\Data\ProductInterfaceFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Type;
use Magento\Framework\Exception\NoSuchEntityException;
use Mercur\Catalog\Model\CatalogConfig;

/**
 * Creates or refreshes a Magento product from a published Mercur product. One
 * simple product per variant; the Mercur product id is kept in metadata so
 * the next import finds it again.
 */
class Importer
{
    public function __construct(
        private readonly ProductRepositoryInterface $products,
        private readonly ProductInterfaceFactory $productFactory,
        private readonly CatalogConfig $config,
        private readonly AttributeWriter $attributes
    ) {
    }

    /**
     * @return string[] SKUs touched
     */
    public function import(array $mercurProduct): array
    {
        $skus = [];
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
                    ->setVisibility($this->config->getImportedProductVisibility())
                    ->setPrice(0)
                    ->setWebsiteIds($this->allWebsiteIds())
                    ->setStockData(['use_config_manage_stock' => 0, 'manage_stock' => 0, 'is_in_stock' => 1]);
            }

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
            $this->products->save($product);
            $skus[] = $sku;
        }

        return $skus;
    }

    private function find(string $sku): ?ProductInterface
    {
        try {
            return $this->products->get($sku, true);
        } catch (NoSuchEntityException) {
            return null;
        }
    }

    /**
     * @return int[]
     */
    private function allWebsiteIds(): array
    {
        return [0];
    }
}
