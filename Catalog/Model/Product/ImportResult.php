<?php

declare(strict_types=1);

namespace Mercur\Catalog\Model\Product;

/**
 * What importing one Mercur product produced: the SKUs it touched and the
 * lines worth showing in the process output.
 */
final class ImportResult
{
    /** @var string[] */
    private array $variantSkus = [];

    /** @var string[] */
    private array $parentSkus = [];

    /** @var string[] */
    private array $lines = [];

    public function __construct(private readonly string $label)
    {
    }

    public function addSku(string $sku, bool $parent = false): void
    {
        if ($parent) {
            $this->parentSkus[] = $sku;
        } else {
            $this->variantSkus[] = $sku;
        }
    }

    public function warn(string $message): void
    {
        $this->lines[] = $this->label . ': ' . $message;
    }

    public function note(string $message): void
    {
        $this->lines[] = $message;
    }

    /**
     * @return string[] every SKU touched, parents last
     */
    public function skus(): array
    {
        return array_merge($this->variantSkus, $this->parentSkus);
    }

    public function variantCount(): int
    {
        return count($this->variantSkus);
    }

    public function parentCount(): int
    {
        return count($this->parentSkus);
    }

    /**
     * @return string[]
     */
    public function lines(): array
    {
        return $this->lines;
    }
}
