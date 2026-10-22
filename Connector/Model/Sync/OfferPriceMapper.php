<?php

declare(strict_types=1);

namespace Mercur\Connector\Model\Sync;

/**
 * Maps a store-API offer priced for one region onto its `mercur_offer_price`
 * row. The calculated price already reflects active price lists; quantity
 * tiers only exist on the raw prices.
 */
class OfferPriceMapper
{
    public function map(array $offer, int $websiteId, string $currencyCode): ?array
    {
        $calculated = $offer['calculated_price'] ?? null;
        $currency = strtoupper((string) ($calculated['currency_code'] ?? $currencyCode));
        if ($currency !== strtoupper($currencyCode)) {
            return null;
        }

        [$base, $ranges] = $this->rawPrices($offer['prices'] ?? [], $currencyCode);
        $price = isset($calculated['calculated_amount']) ? (float) $calculated['calculated_amount'] : $base;
        if ($price === null) {
            return null;
        }

        $original = isset($calculated['original_amount']) ? (float) $calculated['original_amount'] : null;
        $discounted = !empty($calculated['is_calculated_price_price_list']) && $original !== null && $original > $price;

        return [
            'offer_id' => (string) $offer['id'],
            'website_id' => $websiteId,
            'currency_code' => strtoupper($currencyCode),
            'price' => $price,
            'origin_price' => $discounted ? $original : null,
            'price_ranges' => $ranges === [] ? null : json_encode($ranges),
        ];
    }

    /**
     * @return array{0: float|null, 1: array<int, array{min_quantity:int|null,max_quantity:int|null,amount:float}>}
     */
    private function rawPrices(array $prices, string $currencyCode): array
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
            $ranges[] = [
                'min_quantity' => $min === null ? null : (int) $min,
                'max_quantity' => $max === null ? null : (int) $max,
                'amount' => (float) $price['amount'],
            ];
        }

        return [$base, $ranges];
    }
}
