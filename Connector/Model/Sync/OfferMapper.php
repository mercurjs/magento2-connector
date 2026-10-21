<?php

declare(strict_types=1);

namespace Mercur\Connector\Model\Sync;

/**
 * Maps one offer from GET /admin/offers onto a mercur_offer row.
 */
class OfferMapper
{
    public function __construct(private readonly SellerMapper $sellers)
    {
    }

    public function map(array $offer, string $currencyCode): ?array
    {
        $sku = $offer['product_variant']['sku'] ?? null;
        if (!$sku) {
            return null;
        }

        [$price, $ranges] = $this->prices($offer['prices'] ?? [], $currencyCode);
        $seller = $offer['seller'] ?? [];

        return [
            'offer_id' => $offer['id'],
            'product_sku' => $sku,
            'seller_sku' => $offer['sku'] ?? '',
            'seller_id' => $offer['seller_id'],
            'mercur_product_id' => $offer['product_id'] ?? ($offer['product_variant']['product_id'] ?? null),
            'variant_id' => $offer['variant_id'],
            'shipping_profile_id' => $offer['shipping_profile_id'] ?? null,
            'price' => $price,
            'origin_price' => null,
            'currency_code' => $currencyCode,
            'price_ranges' => $ranges === [] ? null : json_encode($ranges),
            'quantity' => $this->quantity($offer['inventory_items'] ?? []),
            'allow_backorder' => (int) ($offer['allow_backorder'] ?? false),
            'manage_inventory' => (int) ($offer['manage_inventory'] ?? true),
            'leadtime_to_ship' => $offer['leadtime_to_ship'] ?? ($seller['default_leadtime_to_ship'] ?? null),
            'condition_code' => $offer['condition']['code'] ?? null,
            'active' => (int) $this->sellers->isSelling($seller),
            'updated_at' => $this->timestamp($offer['updated_at'] ?? null),
        ];
    }

    /**
     * @return array{0: float|null, 1: array}
     */
    private function prices(array $prices, string $currencyCode): array
    {
        $base = null;
        $ranges = [];
        foreach ($prices as $price) {
            if (strtolower((string) ($price['currency_code'] ?? '')) !== strtolower($currencyCode)) {
                continue;
            }
            $min = $price['min_quantity'] ?? null;
            $max = $price['max_quantity'] ?? null;
            if ($min === null && $max === null) {
                $base = (float) $price['amount'];
                continue;
            }
            $ranges[] = ['min_quantity' => $min, 'max_quantity' => $max, 'amount' => (float) $price['amount']];
        }

        return [$base, $ranges];
    }

    private function quantity(array $inventoryItems): ?int
    {
        if ($inventoryItems === []) {
            return null;
        }
        $available = null;
        foreach ($inventoryItems as $link) {
            $required = max(1, (int) ($link['required_quantity'] ?? 1));
            $stocked = 0;
            foreach ($link['inventory_item']['location_levels'] ?? [] as $level) {
                $stocked += (int) ($level['stocked_quantity'] ?? 0) - (int) ($level['reserved_quantity'] ?? 0);
            }
            $units = intdiv(max(0, $stocked), $required);
            $available = $available === null ? $units : min($available, $units);
        }

        return $available ?? 0;
    }


    private function timestamp(?string $iso): ?string
    {
        return $iso ? (new \DateTimeImmutable($iso))->format('Y-m-d H:i:s') : null;
    }
}
