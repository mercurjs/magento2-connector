<?php

declare(strict_types=1);

namespace Mercur\OfferIndexer\Model;

use Mercur\Core\Model\Config\Source\BestOfferRule;

class BestOfferSelector
{
    /**
     * @param Candidate[] $candidates
     */
    public function select(array $candidates, string $rule): ?Candidate
    {
        $available = array_values(array_filter($candidates, static fn (Candidate $c) => $c->available));
        if ($available === []) {
            return null;
        }

        usort($available, static function (Candidate $a, Candidate $b) use ($rule): int {
            if ($rule === BestOfferRule::OPERATOR_FIRST && $a->isOperator() !== $b->isOperator()) {
                return $a->isOperator() ? -1 : 1;
            }
            if ($rule === BestOfferRule::PREMIUM_FIRST && $a->premium !== $b->premium) {
                return $a->premium ? -1 : 1;
            }

            return $a->price <=> $b->price;
        });

        return $available[0];
    }
}
