<?php

declare(strict_types=1);

namespace Mercur\Connector\Model\Checkout;

/**
 * What to do with one marketplace cart line once its offer was re-read.
 */
final class ItemDecision
{
    public const KEEP = 'keep';
    public const REPRICE = 'reprice';
    public const REMOVE = 'remove';
    public const UNAVAILABLE = 'unavailable';
    public const QUANTITY = 'quantity';

    public static function decide(
        bool $offerSellable,
        bool $quantityAvailable,
        float $offerPrice,
        float $linePrice,
        bool $autoRemoveUnavailable
    ): string {
        if (!$offerSellable) {
            return $autoRemoveUnavailable ? self::REMOVE : self::UNAVAILABLE;
        }
        if (!$quantityAvailable) {
            return self::QUANTITY;
        }

        return abs($offerPrice - $linePrice) > 0.00001 ? self::REPRICE : self::KEEP;
    }
}
