<?php

declare(strict_types=1);

namespace Mercur\Connector\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Quote\Model\Quote\Item;
use Mercur\Connector\Model\OfferRepository;
use Mercur\Core\Model\Config;

/**
 * sales_quote_product_add_after: binds a new cart line to its seller offer,
 * prices it from the offer, and labels it with the seller so every native
 * item renderer (cart, minicart, checkout, order, email, PDF) shows it.
 */
class AttachOfferToQuoteItem implements ObserverInterface
{
    public function __construct(
        private readonly OfferRepository $offers,
        private readonly Config $config,
        private readonly Json $json
    ) {
    }

    public function execute(Observer $observer): void
    {
        $items = $observer->getEvent()->getData('items');
        foreach (is_array($items) ? $items : [] as $item) {
            if ($item instanceof Item && !$item->getParentItem()) {
                $this->attach($item);
            }
        }
    }

    private function attach(Item $item): void
    {
        $offerId = $this->offerIdOf($item);
        $quote = $item->getQuote();
        if ($offerId === '' || !$quote || !$this->config->isEnabled((int) $quote->getStoreId())) {
            return;
        }

        $offer = $this->offers->getById($offerId, (int) $quote->getStore()->getWebsiteId());
        if ($offer === null || $offer->getProductSku() !== (string) $item->getSku()) {
            throw new LocalizedException(__('This offer is no longer available.'));
        }
        if (!$offer->isAvailable((int) $item->getQty())) {
            throw new LocalizedException(__('The seller does not have enough stock for this quantity.'));
        }

        $price = $offer->getPriceForQuantity((int) $item->getQty());
        $item->setData('mercur_offer_id', $offer->getOfferId());
        $item->setData('mercur_seller_id', $offer->getSellerId());
        $item->setCustomPrice($price);
        $item->setOriginalCustomPrice($price);
        $item->getProduct()->setIsSuperMode(true);
        $item->addOption([
            'product_id' => $item->getProductId(),
            'code' => 'additional_options',
            'value' => $this->json->serialize([[
                'label' => (string) __('Sold by'),
                'value' => $offer->getSellerName(),
                'mercur_seller_handle' => $offer->getSellerHandle(),
            ]]),
        ]);
    }

    private function offerIdOf(Item $item): string
    {
        $option = $item->getOptionByCode(AddOfferOption::OPTION_CODE);
        if ($option) {
            return (string) $option->getValue();
        }
        foreach ($item->getChildren() as $child) {
            $childOption = $child->getOptionByCode(AddOfferOption::OPTION_CODE);
            if ($childOption) {
                return (string) $childOption->getValue();
            }
        }

        return '';
    }
}
