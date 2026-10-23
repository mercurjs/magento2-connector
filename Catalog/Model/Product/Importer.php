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
use Mercur\Catalog\Model\Product\Image\GalleryPlan;
use Mercur\Catalog\Model\Product\Image\GalleryWriter;
use Mercur\Connector\Model\Catalog\Identifier;
use Mercur\Connector\Model\Catalog\ProductMatcher;
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
        private readonly StoreManagerInterface $storeManager,
        private readonly ProductMatcher $matcher,
        private readonly GalleryWriter $gallery
    ) {
    }

    public function import(array $mercurProduct): ImportResult
    {
        $id = (string) ($mercurProduct['id'] ?? '?');
        $result = new ImportResult(sprintf('%s (%s)', (string) ($mercurProduct['title'] ?? $id), $id));

        $axes = $this->axes->fromProduct($mercurProduct);
        $axisCodes = $axes === null ? [] : $this->configurable->usableAxes($axes, $result);
        if ($axes === null && $this->axes->hasInlineAxis($mercurProduct)) {
            $result->warn('variant axis is product-scoped; variants imported as standalone products');
        }
        $asChildren = $axisCodes !== [];
        $websiteIds = $this->websiteIds();

        $variants = $mercurProduct['variants'] ?? [];
        $this->matcher->prime(array_map(static fn (array $variant) => [
            'sku' => $variant['sku'] ?? null,
            'identifiers' => Identifier::fromVariant($variant),
        ], $variants));

        $children = [];
        foreach ($variants as $variant) {
            $sku = (string) ($variant['sku'] ?? '');
            if ($sku === '') {
                continue;
            }
            $target = $this->matcher->resolve($sku, Identifier::fromVariant($variant));
            if ($target !== null && $target !== $sku) {
                $result->note(sprintf('Variant %s matches your product %s by identifier; not imported', $sku, $target));
                continue;
            }
            $product = $this->find($sku);
            $isNew = $product === null;
            if (!$isNew && (int) $product->getData('mercur_product') !== 1) {
                $result->note(sprintf('Variant %s is one of your own products; not imported', $sku));
                continue;
            }
            $product ??= $this->productFactory->create();
            if ($isNew) {
                $product->setStoreId(0)
                    ->setSku($sku)
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
            if ($this->config->importImages()) {
                $this->gallery->apply($product, GalleryPlan::urlsFor($mercurProduct, $variant, $asChildren), (string) $product->getName(), $result);
            }
            $children[] = $this->products->save($product);
            $result->addSku($sku);
        }

        if ($asChildren && $children !== []) {
            $parentSku = $this->configurable->build($mercurProduct, $children, $axisCodes, $websiteIds, $result);
            if ($parentSku !== null) {
                $result->addSku($parentSku, true);
            }
        }

        return $result;
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

    /**
     * Imported products are written at the default scope, so every store view sees the same product.
     */
    private function find(string $sku): ?ProductInterface
    {
        try {
            return $this->products->get($sku, true, 0);
        } catch (NoSuchEntityException) {
            return null;
        }
    }
}
