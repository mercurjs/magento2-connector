<?php

declare(strict_types=1);

namespace Mercur\Frontend\Model;

use Mercur\Core\Model\Config\Source\BestOfferRule;

/**
 * Decides whether a listing price box shows the operator's own price (null)
 * or a seller offer's, using the live own price rather than the indexed one
 * so catalog rules and customer group prices still win when they are lower.
 */
class ListingPriceChooser
{
    /**
     * @param array<string, mixed> $summary a mercur_offer_product_index row
     * @return array{price: float, from: bool}|null
     */
    public function choose(?float $ownFinal, bool $ownSalable, array $summary, string $rule): ?array
    {
        $offerCount = (int) ($summary['offer_count'] ?? 0);
        $best = $summary['best_price'] ?? null;
        if ($offerCount < 1 || $best === null || !empty($summary['operator_available']) && $ownSalable && $rule === BestOfferRule::OPERATOR_FIRST) {
            return null;
        }
        $best = (float) $best;
        if ($ownSalable && $ownFinal !== null && $ownFinal > 0 && $ownFinal <= $best) {
            return null;
        }

        $min = $summary['min_price'] === null ? $best : min((float) $summary['min_price'], $best);
        $max = $summary['max_price'] === null ? $best : (float) $summary['max_price'];
        if ($ownSalable && $ownFinal !== null && $ownFinal > 0) {
            $max = max($max, $ownFinal);
        }
        $ways = $offerCount + ($ownSalable && $ownFinal !== null && $ownFinal > 0 ? 1 : 0);

        return ['price' => $min, 'from' => $ways > 1 && $min < $max];
    }
}
