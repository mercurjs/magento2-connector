<?php

declare(strict_types=1);

namespace Mercur\Connector\Model\Tax;

/**
 * Tax figures for the marketplace shipping of one sales document.
 */
final class SellerShippingTax
{
    /**
     * Splits a seller's quoted amount into net, tax and gross for a rate in percent.
     *
     * @return array{excl: float, tax: float, incl: float}
     */
    public static function split(float $amount, float $percent, bool $includesTax): array
    {
        if ($amount <= 0 || $percent <= 0) {
            return ['excl' => $amount, 'tax' => 0.0, 'incl' => $amount];
        }
        if ($includesTax) {
            $excl = $amount / (1 + $percent / 100);

            return ['excl' => round($excl, 4), 'tax' => round($amount - $excl, 4), 'incl' => $amount];
        }
        $tax = $amount * $percent / 100;

        return ['excl' => $amount, 'tax' => round($tax, 4), 'incl' => round($amount + $tax, 4)];
    }

    /**
     * The share of the order's shipping tax that belongs to a document billing or refunding part of the shipping.
     *
     * @return array{tax: float, incl: float}
     */
    public static function prorate(float $documentAmount, float $orderAmount, float $orderTax): array
    {
        if ($orderAmount <= 0 || $documentAmount <= 0) {
            return ['tax' => 0.0, 'incl' => max(0.0, $documentAmount)];
        }
        $tax = abs($documentAmount - $orderAmount) < 0.00001 ? $orderTax : round($orderTax * $documentAmount / $orderAmount, 4);

        return ['tax' => $tax, 'incl' => round($documentAmount + $tax, 4)];
    }
}
