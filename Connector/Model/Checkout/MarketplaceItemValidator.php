<?php

declare(strict_types=1);

namespace Mercur\Connector\Model\Checkout;

use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item;
use Mercur\Connector\Model\OfferRepository;

/**
 * Re-checks every marketplace line whenever totals are collected: the price
 * follows quantity tiers, and a line whose offer went away or ran short
 * blocks checkout until the shopper fixes it.
 */
class MarketplaceItemValidator
{
    public const ERROR_ORIGIN = 'mercur';
    private const ERROR_UNAVAILABLE = 1;
    private const ERROR_QUANTITY = 2;

    public function __construct(private readonly OfferRepository $offers)
    {
    }

    public function validate(Quote $quote): void
    {
        $quote->removeErrorInfosByParams(null, ['origin' => self::ERROR_ORIGIN]);

        $items = array_filter(
            $quote->getAllVisibleItems(),
            static fn (Item $item) => (bool) $item->getData('mercur_offer_id') && !$item->isDeleted()
        );
        if ($items === []) {
            return;
        }

        $offers = $this->offers->getByIds(
            array_map(static fn (Item $item) => (string) $item->getData('mercur_offer_id'), $items),
            (int) $quote->getStore()->getWebsiteId()
        );

        foreach ($items as $item) {
            $item->removeErrorInfosByParams(['origin' => self::ERROR_ORIGIN]);
            $offer = $offers[(string) $item->getData('mercur_offer_id')] ?? null;
            $qty = (int) $item->getQty();

            if ($offer === null || !$offer->isActive()) {
                $this->fail($quote, $item, self::ERROR_UNAVAILABLE, (string) __('This seller offer is no longer available.'));
                continue;
            }
            if (!$offer->isAvailable($qty)) {
                $this->fail($quote, $item, self::ERROR_QUANTITY, (string) __(
                    '%1 can only ship %2 of this item.',
                    $offer->getSellerName(),
                    (int) $offer->getQuantity()
                ));
                continue;
            }

            $price = $offer->getPriceForQuantity($qty);
            if ((float) $item->getCustomPrice() !== $price) {
                $item->setCustomPrice($price);
                $item->setOriginalCustomPrice($price);
                $item->getProduct()->setIsSuperMode(true);
            }
        }
    }

    private function fail(Quote $quote, Item $item, int $code, string $message): void
    {
        $item->addErrorInfo(self::ERROR_ORIGIN, $code, $message);
        $quote->addErrorInfo('error', self::ERROR_ORIGIN, $code, $message);
    }
}
