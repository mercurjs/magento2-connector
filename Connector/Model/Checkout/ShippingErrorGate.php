<?php

declare(strict_types=1);

namespace Mercur\Connector\Model\Checkout;

/**
 * Whether a quote may move on to payment given the outcome of the last
 * marketplace shipping quote.
 */
final class ShippingErrorGate
{
    public const ERROR_CODE = 3;

    public static function isBlocked(?string $error, bool $hasMarketplaceItems): bool
    {
        return $hasMarketplaceItems && trim((string) $error) !== '';
    }
}
