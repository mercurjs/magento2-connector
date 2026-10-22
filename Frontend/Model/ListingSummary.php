<?php

declare(strict_types=1);

namespace Mercur\Frontend\Model;

use Mercur\OfferIndexer\Model\ResourceModel\ProductIndex;

/**
 * Index rows for the products of the current request, fetched once per
 * listing rather than once per price box.
 */
class ListingSummary
{
    /** @var array<int, array<string, array<string, mixed>|null>> website id => sku => row */
    private array $cache = [];

    public function __construct(private readonly ProductIndex $index)
    {
    }

    /**
     * @param string[] $skus
     */
    public function prime(array $skus, int $websiteId): void
    {
        $missing = [];
        foreach ($skus as $sku) {
            $sku = (string) $sku;
            if ($sku !== '' && !array_key_exists($sku, $this->cache[$websiteId] ?? [])) {
                $missing[] = $sku;
            }
        }
        if ($missing === []) {
            return;
        }
        $rows = $this->index->getMany($missing, $websiteId);
        foreach ($missing as $sku) {
            $this->cache[$websiteId][$sku] = $rows[$sku] ?? null;
        }
    }

    public function get(string $sku, int $websiteId): ?array
    {
        $this->prime([$sku], $websiteId);

        return $this->cache[$websiteId][$sku] ?? null;
    }
}
