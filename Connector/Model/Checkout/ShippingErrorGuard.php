<?php

declare(strict_types=1);

namespace Mercur\Connector\Model\Checkout;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;
use Magento\Quote\Model\Quote;

/**
 * Throws when the quote's last marketplace shipping quote failed.
 */
class ShippingErrorGuard
{
    public function __construct(
        private readonly CartRepositoryInterface $quotes,
        private readonly MaskedQuoteIdToQuoteIdInterface $maskedIds,
        private readonly ShadowCart $shadowCart
    ) {
    }

    /**
     * @throws LocalizedException
     */
    public function assertMaskedId(string $maskedId): void
    {
        try {
            $this->assertQuoteId($this->maskedIds->execute($maskedId));
        } catch (NoSuchEntityException) {
            return;
        }
    }

    /**
     * @throws LocalizedException
     */
    public function assertQuoteId(int $quoteId): void
    {
        if ($quoteId <= 0) {
            return;
        }
        try {
            $quote = $this->quotes->get($quoteId);
        } catch (NoSuchEntityException) {
            return;
        }
        if ($quote instanceof Quote) {
            $this->assertQuote($quote);
        }
    }

    /**
     * @throws LocalizedException
     */
    public function assertQuote(Quote $quote): void
    {
        $error = $quote->getShippingAddress()->getData('mercur_shipping_error');
        $error = $error === null ? null : (string) $error;
        if (ShippingErrorGate::isBlocked($error, $this->shadowCart->hasMarketplaceItems($quote))) {
            throw new LocalizedException(__((string) $error));
        }
    }
}
