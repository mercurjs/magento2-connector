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

    public function map(array $offer): ?array
    {
        $sku = $offer['product_variant']['sku'] ?? null;
        if (!$sku) {
            return null;
        }

        $seller = $offer['seller'] ?? [];

        return [
            'offer_id' => $offer['id'],
            'product_sku' => $sku,
            'seller_sku' => $offer['sku'] ?? '',
            'seller_id' => $offer['seller_id'],
            'mercur_product_id' => $offer['product_id'] ?? ($offer['product_variant']['product_id'] ?? null),
            'variant_id' => $offer['variant_id'],
            'shipping_profile_id' => $offer['shipping_profile_id'] ?? null,
            'quantity' => $this->quantity($offer['inventory_items'] ?? []),
            'allow_backorder' => (int) ($offer['allow_backorder'] ?? false),
            'manage_inventory' => (int) ($offer['manage_inventory'] ?? true),
            'leadtime_to_ship' => $offer['leadtime_to_ship'] ?? ($seller['default_leadtime_to_ship'] ?? null),
            'condition_code' => $offer['condition']['code'] ?? null,
            'condition_label' => $offer['condition']['label'] ?? null,
            'active' => (int) $this->sellers->isSelling($seller),
            'updated_at' => $this->timestamp($offer['updated_at'] ?? null),
        ];
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

        return $available;
    }


    private function timestamp(?string $iso): ?string
    {
        return $iso ? (new \DateTimeImmutable($iso))->format('Y-m-d H:i:s') : null;
    }
}
