<?php

declare(strict_types=1);

namespace Mercur\Connector\Model\Catalog;

use Mercur\Connector\Model\ResourceModel\Product\IdentifierLookup;
use Mercur\Core\Model\Config;

/**
 * Resolves the Magento SKU a Mercur variant stands for: the variant SKU when
 * Magento has it, otherwise the operator product carrying the same EAN, UPC or
 * barcode in one of the configured identifier attributes. Lookups are batched
 * per page through prime() and cached, misses included, for the run.
 */
final class ProductMatcher
{
    /** @var array<string, bool> */
    private array $knownSkus = [];

    /** @var array<string, bool> */
    private array $checkedSkus = [];

    /** @var array<string, string> */
    private array $byIdentifier = [];

    /** @var array<string, bool> */
    private array $checkedIdentifiers = [];

    public function __construct(
        private readonly IdentifierLookup $lookup,
        private readonly Config $config
    ) {
    }

    /**
     * @param array<int, array{sku: ?string, identifiers: string[]}> $candidates
     */
    public function prime(array $candidates): void
    {
        $skus = [];
        $identifiers = [];
        foreach ($candidates as $candidate) {
            $sku = (string) ($candidate['sku'] ?? '');
            if ($sku !== '' && !isset($this->checkedSkus[$sku])) {
                $skus[$sku] = true;
            }
            foreach ($candidate['identifiers'] as $identifier) {
                if (!isset($this->checkedIdentifiers[$identifier])) {
                    $identifiers[$identifier] = true;
                }
            }
        }

        // Numeric SKUs and identifiers turn into integer array keys; the lookups need strings.
        if ($skus !== []) {
            foreach ($this->lookup->existingSkus(array_map('strval', array_keys($skus))) as $sku) {
                $this->knownSkus[$sku] = true;
            }
            $this->checkedSkus += $skus;
        }

        $codes = $this->config->getIdentifierAttributes();
        if ($identifiers !== [] && $codes !== []) {
            $forms = [];
            foreach (array_keys($identifiers) as $identifier) {
                foreach (Identifier::forms((string) $identifier) as $form) {
                    $forms[$form] = true;
                }
            }
            $this->byIdentifier += $this->lookup->skusByIdentifier($codes, array_map('strval', array_keys($forms)));
            $this->checkedIdentifiers += $identifiers;
        }
    }

    /**
     * @param string[] $identifiers canonical identifiers of the variant
     */
    public function resolve(?string $sku, array $identifiers): ?string
    {
        $sku = (string) $sku;
        if ($sku !== '') {
            if (!isset($this->checkedSkus[$sku])) {
                $this->prime([['sku' => $sku, 'identifiers' => []]]);
            }
            if (isset($this->knownSkus[$sku])) {
                return $sku;
            }
        }
        if ($identifiers === [] || $this->config->getIdentifierAttributes() === []) {
            return null;
        }

        $missing = array_filter($identifiers, fn (string $identifier) => !isset($this->checkedIdentifiers[$identifier]));
        if ($missing !== []) {
            $this->prime([['sku' => null, 'identifiers' => array_values($missing)]]);
        }
        foreach ($identifiers as $identifier) {
            if (isset($this->byIdentifier[$identifier])) {
                return $this->byIdentifier[$identifier];
            }
        }

        return null;
    }
}
