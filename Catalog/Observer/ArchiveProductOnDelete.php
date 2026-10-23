<?php

declare(strict_types=1);

namespace Mercur\Catalog\Observer;

use Magento\Catalog\Model\Product;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Mercur\Catalog\Model\CatalogConfig;
use Mercur\Catalog\Model\Task\ProductPush;
use Mercur\Core\Model\Config;
use Mercur\Process\Model\Runner;

/**
 * A deleted operator product goes back to draft in Mercur. The SKUs are read
 * now, before the delete, because the task runs once the product is gone.
 */
class ArchiveProductOnDelete implements ObserverInterface
{
    public function __construct(
        private readonly Runner $runner,
        private readonly Config $config,
        private readonly CatalogConfig $catalogConfig
    ) {
    }

    public function execute(Observer $observer): void
    {
        /** @var Product|null $product */
        $product = $observer->getEvent()->getProduct();
        if (!$product || (int) $product->getData('mercur_product') === 1) {
            return;
        }
        if (!$this->config->isEnabled() || !$this->catalogConfig->exportProducts()) {
            return;
        }

        $skus = [(string) $product->getSku()];
        if ($product->getTypeId() === Configurable::TYPE_CODE) {
            foreach ($product->getTypeInstance()->getUsedProducts($product) as $child) {
                $skus[] = (string) $child->getSku();
            }
        }
        $this->runner->enqueue(ProductPush::CODE, ['archive' => array_values(array_filter($skus))]);
    }
}
