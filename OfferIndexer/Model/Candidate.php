<?php

declare(strict_types=1);

namespace Mercur\OfferIndexer\Model;

/**
 * One way to buy a SKU: a seller offer, or the operator's own stock (offerId null).
 */
final class Candidate
{
    public function __construct(
        public readonly ?string $offerId,
        public readonly ?string $sellerId,
        public readonly float $price,
        public readonly bool $premium,
        public readonly bool $available
    ) {
    }

    public static function operator(float $price, bool $available): self
    {
        return new self(null, null, $price, false, $available);
    }

    public function isOperator(): bool
    {
        return $this->offerId === null;
    }
}
