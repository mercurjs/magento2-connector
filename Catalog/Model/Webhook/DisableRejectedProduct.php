<?php

declare(strict_types=1);

namespace Mercur\Catalog\Model\Webhook;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Mercur\Connector\Model\Webhook\HandlerInterface;

/**
 * product.rejected: a product the operator rejected in Mercur stops selling in
 * Magento. It is disabled, not deleted, so past orders keep their product.
 */
class DisableRejectedProduct implements HandlerInterface
{
    public function __construct(
        private readonly CollectionFactory $productCollection,
        private readonly ProductRepositoryInterface $products
    ) {
    }

    public function handle(string $event, array $data): void
    {
        $mercurProductId = (string) ($data['id'] ?? '');
        if ($mercurProductId === '') {
            return;
        }
        $collection = $this->productCollection->create()
            ->addAttributeToFilter('mercur_product_id', $mercurProductId)
            ->addAttributeToFilter('status', Status::STATUS_ENABLED);
        foreach ($collection as $product) {
            $loaded = $this->products->getById((int) $product->getId(), true, 0);
            $loaded->setStatus(Status::STATUS_DISABLED);
            $this->products->save($loaded);
        }
    }
}
