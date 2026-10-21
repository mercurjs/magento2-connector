<?php

declare(strict_types=1);

namespace Mercur\Connector\Observer;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote\Item;
use Mercur\Connector\Model\OfferRepository;

/**
 * An add-to-cart request carrying `mercur_offer_id` turns the quote item into a
 * marketplace line priced by the offer. Without the parameter the line is the
 * operator's own stock and Magento prices it as usual.
 */
class AddOfferToCartItem implements ObserverInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly OfferRepository $offers
    ) {
    }

    public function execute(Observer $observer): void
    {
        $offerId = (string) $this->request->getParam('mercur_offer_id', '');
        if ($offerId === '') {
            return;
        }

        /** @var Item $item */
        $item = $observer->getEvent()->getData('quote_item');
        $offer = $this->offers->getById($offerId);

        if ($offer === null || $offer->getProductSku() !== $item->getProduct()->getSku()) {
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
    }
}
