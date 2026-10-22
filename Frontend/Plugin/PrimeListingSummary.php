<?php

declare(strict_types=1);

namespace Mercur\Frontend\Plugin;

use Magento\Catalog\Block\Product\ListProduct;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Mercur\Frontend\Model\ListingSummary;

class PrimeListingSummary
{
    public function __construct(private readonly ListingSummary $summaries)
    {
    }

    /**
     * @param Collection $result
     * @return Collection
     */
    public function afterGetLoadedProductCollection(ListProduct $subject, $result)
    {
        if (!$result instanceof Collection || !$result->isLoaded()) {
            return $result;
        }
        $skus = [];
        foreach ($result as $product) {
            $skus[] = (string) $product->getSku();
        }
        $this->summaries->prime($skus, (int) $result->getStore()->getWebsiteId());

        return $result;
    }
}
