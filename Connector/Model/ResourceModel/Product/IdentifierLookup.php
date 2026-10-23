<?php

declare(strict_types=1);

namespace Mercur\Connector\Model\ResourceModel\Product;

use Magento\Catalog\Api\ProductAttributeRepositoryInterface;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Mercur\Connector\Model\Catalog\Identifier;

/**
 * Finds the operator's products by SKU or by the values of the configured
 * identifier attributes.
 */
class IdentifierLookup
{
    public function __construct(
        private readonly CollectionFactory $products,
        private readonly ProductAttributeRepositoryInterface $attributes
    ) {
    }

    /**
     * @param string[] $skus
     * @return string[] the SKUs that exist
     */
    public function existingSkus(array $skus): array
    {
        if ($skus === []) {
            return [];
        }
        $collection = $this->products->create()->addFieldToFilter('sku', ['in' => array_values($skus)]);

        return array_map('strval', $collection->getColumnValues('sku'));
    }

    /**
     * @param string[] $codes attribute codes holding identifiers
     * @param string[] $values spellings to look for
     * @return array<string, string> canonical identifier => SKU; the oldest product wins a duplicate
     */
    public function skusByIdentifier(array $codes, array $values): array
    {
        $codes = array_values(array_filter($codes, fn (string $code) => $this->exists($code)));
        if ($codes === [] || $values === []) {
            return [];
        }

        $collection = $this->products->create()->addAttributeToSelect($codes);
        $collection->addAttributeToFilter(array_map(
            static fn (string $code) => ['attribute' => $code, 'in' => array_values($values)],
            $codes
        ));
        $collection->setOrder('entity_id', 'ASC');

        $map = [];
        foreach ($collection as $product) {
            foreach ($codes as $code) {
                $key = Identifier::canonical((string) $product->getData($code));
                if ($key !== null && !isset($map[$key])) {
                    $map[$key] = (string) $product->getSku();
                }
            }
        }

        return $map;
    }

    private function exists(string $code): bool
    {
        try {
            $this->attributes->get($code);

            return true;
        } catch (NoSuchEntityException) {
            return false;
        }
    }
}
