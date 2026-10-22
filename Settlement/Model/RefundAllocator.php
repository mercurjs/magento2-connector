<?php

declare(strict_types=1);

namespace Mercur\Settlement\Model;

/**
 * Splits a Magento credit memo into the amount each Mercur seller order gave
 * back: its refunded lines, plus a share of the refunded marketplace shipping
 * weighted by what each seller charged for shipping.
 */
final class RefundAllocator
{
    /**
     * @param array<int, array{seller_order: string, seller: string, amount: float}> $lines refunded marketplace lines
     * @param array<string, float> $shippingBySeller seller id => shipping the seller charged
     * @return array<string, float> seller order id => amount, rounded to cents
     */
    public function allocate(array $lines, float $marketplaceShipping, array $shippingBySeller): array
    {
        $amounts = [];
        $sellerOrders = [];
        foreach ($lines as $line) {
            $amounts[$line['seller_order']] = ($amounts[$line['seller_order']] ?? 0.0) + $line['amount'];
            $sellerOrders[$line['seller']] = $line['seller_order'];
        }

        if ($marketplaceShipping > 0 && $sellerOrders !== []) {
            $weights = [];
            foreach ($sellerOrders as $seller => $sellerOrder) {
                $weights[$sellerOrder] = max(0.0, $shippingBySeller[$seller] ?? 0.0);
            }
            $total = array_sum($weights);
            foreach ($weights as $sellerOrder => $weight) {
                $share = $total > 0 ? $weight / $total : 1 / count($weights);
                $amounts[$sellerOrder] = ($amounts[$sellerOrder] ?? 0.0) + $marketplaceShipping * $share;
            }
        }

        return array_filter(array_map(static fn (float $amount) => round($amount, 2), $amounts), static fn (float $amount) => $amount > 0);
    }
}
