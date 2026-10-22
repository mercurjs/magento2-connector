<?php

declare(strict_types=1);

namespace Mercur\Connector\Plugin\Search;

use Magento\CatalogSearch\Model\Indexer\Fulltext\Action\DataProvider;
use Magento\CatalogSearch\Model\Indexer\Plugin\StockedProductsFilterPlugin;
use Mercur\Connector\Model\ResourceModel\Offer\IndexedSkus;
use Mercur\Core\Model\Config;
use Mercur\Core\Model\Scope\WebsiteScope;

/**
 * Magento drops out-of-stock products from the fulltext index when they are
 * hidden; products a seller offers go back in so search still finds them.
 */
class IncludeOfferedProductsInFulltext
{
    public function __construct(
        private readonly IndexedSkus $indexedSkus,
        private readonly WebsiteScope $websites,
        private readonly Config $config
    ) {
    }

    /**
     * @param array{0: array, 1: array, 2: int} $result
     * @return array{0: array, 1: array, 2: int}
     */
    public function afterBeforePrepareProductIndex(
        StockedProductsFilterPlugin $subject,
        array $result,
        DataProvider $dataProvider,
        array $indexData,
        array $productData,
        int $storeId
    ): array {
        if (!$this->config->isEnabled($storeId)) {
            return $result;
        }
        $dropped = array_diff_key($indexData, $result[0]);
        if ($dropped === []) {
            return $result;
        }
        $offered = $this->indexedSkus->productIdsAmong(array_keys($dropped), $this->websites->websiteIdForStore($storeId));
        foreach ($offered as $productId) {
            $result[0][$productId] = $indexData[$productId];
        }

        return $result;
    }
}
