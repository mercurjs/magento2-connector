<?php

declare(strict_types=1);

namespace Mercur\Connector\Model\Order;

/**
 * Maps the sellers of a Mercur order group to their child orders. The group
 * returned at cart completion carries no seller on its child orders, so a
 * lookup fills in the ones it leaves out.
 */
final class SellerOrderMap
{
    /**
     * @param array<int, array> $orders the group's child orders
     * @param callable(string): ?string $sellerOf resolves a child order id to its seller id
     * @return array<string, string> seller id => child order id
     */
    public static function build(array $orders, callable $sellerOf): array
    {
        $bySeller = [];
        foreach ($orders as $child) {
            $orderId = (string) ($child['id'] ?? '');
            if ($orderId === '') {
                continue;
            }
            $sellerId = $child['seller_id'] ?? ($child['seller']['id'] ?? null) ?? $sellerOf($orderId);
            if ($sellerId) {
                $bySeller[(string) $sellerId] = $orderId;
            }
        }

        return $bySeller;
    }
}
