<?php

declare(strict_types=1);

namespace Mercur\Connector\Observer;

use Magento\Catalog\Model\Product;
use Magento\Framework\DataObject;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Mercur\Connector\Model\Inventory\ProductSalability;
use Mercur\Core\Model\Config;
use Mercur\Core\Model\Scope\WebsiteScope;
use Mercur\OfferIndexer\Model\BuyBox;

/**
 * catalog_product_type_prepare_full_options: turns `mercur_offer_id` from the
 * buy request into a product option, which keeps two offers of one SKU on
 * separate cart lines. Runs for every add path (cart page, REST, GraphQL,
 * reorder) because they all build a buy request. When none is given and the
 * operator cannot sell the SKU, the best seller offer is used.
 */
class AddOfferOption implements ObserverInterface
{
    public const OPTION_CODE = 'mercur_offer_id';

    public function __construct(
        private readonly BuyBox $buyBox,
        private readonly WebsiteScope $websites,
        private readonly Config $config
    ) {
    }

    public function execute(Observer $observer): void
    {
        $product = $observer->getEvent()->getData('product');
        $buyRequest = $observer->getEvent()->getData('buy_request');
        if (!$product instanceof Product || !$buyRequest instanceof DataObject
            || !$this->config->isEnabled((int) $product->getStoreId())) {
            return;
        }

        $offerId = (string) $buyRequest->getData(self::OPTION_CODE);
        if ($offerId === '' && $product->getTypeId() === 'simple' && $this->operatorCannotSell($product)) {
            $offerId = (string) ($this->buyBox->bestOffer(
                (string) $product->getSku(),
                $this->websites->currentWebsiteId()
            )?->getOfferId() ?? '');
            if ($offerId !== '') {
                $buyRequest->setData(self::OPTION_CODE, $offerId);
            }
        }

        if ($offerId !== '') {
            $product->addCustomOption(self::OPTION_CODE, $offerId);
        }
    }

    private function operatorCannotSell(Product $product): bool
    {
        $product->isSalable();

        return $product->hasData(ProductSalability::OWN_SALABLE)
            && !$product->getData(ProductSalability::OWN_SALABLE);
    }
}
