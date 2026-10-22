<?php

declare(strict_types=1);

namespace Mercur\OfferIndexer\Model;

/**
 * Turns the candidates of one SKU, or the index rows of a configurable's
 * children, into the columns of a mercur_offer_product_index row.
 */
class RollupCalculator
{
    public function __construct(private readonly BestOfferSelector $selector = new BestOfferSelector())
    {
    }

    /**
     * @param Candidate[] $candidates
     * @return array{best_offer_id: string|null, best_seller_id: string|null, best_price: float|null, min_price: float|null, max_price: float|null, offer_count: int, operator_available: int}
     */
    public function summarise(array $candidates, string $rule): array
    {
        $best = $this->selector->select($candidates, $rule);
        $prices = [];
        $offerCount = 0;
        $operatorAvailable = false;
        foreach ($candidates as $candidate) {
            if (!$candidate->available) {
                continue;
            }
            $prices[] = $candidate->price;
            if ($candidate->isOperator()) {
                $operatorAvailable = true;
            } else {
                $offerCount++;
            }
        }

        return [
            'best_offer_id' => $best?->offerId,
            'best_seller_id' => $best?->sellerId,
            'best_price' => $best?->price,
            'min_price' => $prices === [] ? null : min($prices),
            'max_price' => $prices === [] ? null : max($prices),
            'offer_count' => $offerCount,
            'operator_available' => (int) $operatorAvailable,
        ];
    }

    /**
     * A configurable inherits the cheapest best of its children, the price
     * span across them, and the sum of their available offers.
     *
     * @param array<int, array<string, mixed>> $childRows
     * @return array{best_offer_id: string|null, best_seller_id: string|null, best_price: float|null, min_price: float|null, max_price: float|null, offer_count: int, operator_available: int}
     */
    public function parent(array $childRows): array
    {
        $best = null;
        $min = null;
        $max = null;
        $offerCount = 0;
        $operatorAvailable = false;
        foreach ($childRows as $row) {
            if ($row['best_price'] !== null && ($best === null || (float) $row['best_price'] < (float) $best['best_price'])) {
                $best = $row;
            }
            if ($row['min_price'] !== null) {
                $min = $min === null ? (float) $row['min_price'] : min($min, (float) $row['min_price']);
            }
            if ($row['max_price'] !== null) {
                $max = $max === null ? (float) $row['max_price'] : max($max, (float) $row['max_price']);
            }
            $offerCount += (int) ($row['offer_count'] ?? 0);
            $operatorAvailable = $operatorAvailable || (bool) ($row['operator_available'] ?? false);
        }

        return [
            'best_offer_id' => $best === null ? null : ($best['best_offer_id'] === null ? null : (string) $best['best_offer_id']),
            'best_seller_id' => $best === null ? null : ($best['best_seller_id'] === null ? null : (string) $best['best_seller_id']),
            'best_price' => $best === null ? null : (float) $best['best_price'],
            'min_price' => $min,
            'max_price' => $max,
            'offer_count' => $offerCount,
            'operator_available' => (int) $operatorAvailable,
        ];
    }
}
