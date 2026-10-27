<?php

declare(strict_types=1);

namespace Mercur\Connector\Model\Order;

/**
 * The order's shipping description names what each seller ships with, so the
 * success page, emails and order views stop showing the placeholder carrier.
 */
class ShippingDescription
{
    /**
     * @param array<string, mixed> $chosen   seller id => ['name' => option name, ...], as stored in mercur_shipping_methods
     * @param array<string, string> $sellerNames seller id => display name
     * @param string|null $ownDescription Magento's description for the operator's own lines, null when the cart has none
     */
    public static function compose(array $chosen, array $sellerNames, ?string $ownDescription): ?string
    {
        $parts = [];
        if ($ownDescription !== null && $ownDescription !== '') {
            $parts[] = $ownDescription;
        }
        foreach ($chosen as $sellerId => $method) {
            $seller = $sellerNames[(string) $sellerId] ?? (string) $sellerId;
            $name = is_array($method) ? trim((string) ($method['name'] ?? '')) : '';
            $parts[] = $name === '' ? $seller : $seller . ': ' . $name;
        }

        return $parts === [] ? null : implode(', ', $parts);
    }
}
