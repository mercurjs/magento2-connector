<?php

declare(strict_types=1);

namespace Mercur\Connector\Model\Catalog;

/**
 * Product identifiers (EAN, UPC, GTIN) in the form both sides are compared
 * on. Numeric codes lose their leading zeros, so a UPC-12 and the EAN-13
 * written with a leading zero are the same key.
 */
final class Identifier
{
    public static function canonical(?string $raw): ?string
    {
        $value = trim((string) $raw);
        if ($value === '') {
            return null;
        }
        if (ctype_digit($value)) {
            $value = ltrim($value, '0');

            return $value === '' ? null : $value;
        }

        return strtoupper($value);
    }

    /**
     * Every spelling of one canonical identifier worth matching against stored values.
     *
     * @return string[]
     */
    public static function forms(string $canonical): array
    {
        $forms = [$canonical];
        if (ctype_digit($canonical)) {
            foreach ([12, 13, 14] as $length) {
                if (strlen($canonical) < $length) {
                    $forms[] = str_pad($canonical, $length, '0', STR_PAD_LEFT);
                }
            }
        }

        return $forms;
    }

    /**
     * @return string[] canonical identifiers of an /admin/offers row, the offer's own first
     */
    public static function fromOffer(array $offer): array
    {
        $variant = $offer['product_variant'] ?? [];

        return self::collect([
            $offer['ean'] ?? null,
            $offer['upc'] ?? null,
            $variant['ean'] ?? null,
            $variant['upc'] ?? null,
            $variant['barcode'] ?? null,
        ]);
    }

    /**
     * @return string[] canonical identifiers of a product variant
     */
    public static function fromVariant(array $variant): array
    {
        return self::collect([$variant['ean'] ?? null, $variant['upc'] ?? null, $variant['barcode'] ?? null]);
    }

    /**
     * @param array<int, string|null> $raw
     * @return string[]
     */
    private static function collect(array $raw): array
    {
        $result = [];
        foreach ($raw as $value) {
            $key = self::canonical($value);
            if ($key !== null && !in_array($key, $result, true)) {
                $result[] = $key;
            }
        }

        return $result;
    }
}
