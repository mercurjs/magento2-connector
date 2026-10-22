<?php

declare(strict_types=1);

namespace Mercur\Catalog\Observer;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Mercur\Catalog\Model\Product\OfferStockUpdater;

/**
 * mercur_offers_imported: after every offer import, a full one re-checking every SKU that has offers.
 */
class UpdateImportedStock implements ObserverInterface
{
    public function __construct(
        private readonly OfferStockUpdater $updater,
        private readonly ResourceConnection $resource
    ) {
    }

    public function execute(Observer $observer): void
    {
        $skus = $observer->getEvent()->getData('skus');
        $skus = is_array($skus) ? $skus : [];
        if ($observer->getEvent()->getData('full')) {
            $connection = $this->resource->getConnection();
            $skus = array_merge($skus, $connection->fetchCol(
                $connection->select()->distinct()->from($this->resource->getTableName('mercur_offer'), ['product_sku'])
            ));
        }
        foreach (array_chunk(array_values(array_unique($skus)), 500) as $chunk) {
            $this->updater->update($chunk);
        }
    }
}
