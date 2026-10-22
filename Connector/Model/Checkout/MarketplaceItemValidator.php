<?php

declare(strict_types=1);

namespace Mercur\Connector\Model\Checkout;

use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item;
use Mercur\Connector\Model\OfferRepository;
use Mercur\Core\Model\Config;

/**
 * Re-checks every marketplace line whenever totals are collected: the price
 * follows quantity tiers (the shopper is told when it moved), and a line whose
 * offer went away is dropped or, when configured, blocks checkout until the
 * shopper removes it. A short seller stock always blocks.
 */
class MarketplaceItemValidator
{
    public const ERROR_ORIGIN = 'mercur';
    private const ERROR_UNAVAILABLE = 1;
    private const ERROR_QUANTITY = 2;

    public function __construct(
        private readonly OfferRepository $offers,
        private readonly Config $config
    ) {
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
        $autoRemove = $this->config->autoRemoveUnavailableItems((int) $quote->getStoreId());

        foreach ($items as $item) {
            $item->removeErrorInfosByParams(['origin' => self::ERROR_ORIGIN]);
            $offer = $offers[(string) $item->getData('mercur_offer_id')] ?? null;
            $qty = (int) $item->getQty();
            $price = $offer ? $offer->getPriceForQuantity($qty) : 0.0;

            $decision = ItemDecision::decide(
                $offer !== null && $offer->isActive(),
                $offer !== null && $offer->isAvailable($qty),
                $price,
                (float) $item->getCustomPrice(),
                $autoRemove
            );

            switch ($decision) {
                case ItemDecision::REMOVE:
                    $quote->removeItem((int) $item->getId());
                    $quote->addMessage((string) __('%1 was removed from your cart because the seller offer is no longer available.', $item->getName()), 'notice');
                    break;
                case ItemDecision::UNAVAILABLE:
                    $this->fail($quote, $item, self::ERROR_UNAVAILABLE, (string) __('This seller offer is no longer available.'));
                    break;
                case ItemDecision::QUANTITY:
                    $this->fail($quote, $item, self::ERROR_QUANTITY, (string) __(
                        '%1 can only ship %2 of this item.',
                        $offer?->getSellerName(),
                        (int) $offer?->getQuantity()
                    ));
                    break;
                case ItemDecision::REPRICE:
                    $this->reprice($item, $price);
                    if ($item->getId()) {
                        $quote->addMessage((string) __('The price of %1 changed to %2.', $item->getName(), $quote->getStore()->getCurrentCurrency()->format($price, [], false)), 'notice');
                    }
                    break;
            }
        }
    }

    private function reprice(Item $item, float $price): void
    {
        $item->setCustomPrice($price);
        $item->setOriginalCustomPrice($price);
        $item->getProduct()->setIsSuperMode(true);
    }

    private function fail(Quote $quote, Item $item, int $code, string $message): void
    {
        $item->addErrorInfo(self::ERROR_ORIGIN, $code, $message);
        $quote->addErrorInfo('error', self::ERROR_ORIGIN, $code, $message);
    }
}
