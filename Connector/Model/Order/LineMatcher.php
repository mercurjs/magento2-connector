<?php

declare(strict_types=1);

namespace Mercur\Connector\Model\Order;

/**
 * Pairs Magento order lines with the lines of a Mercur seller order. The
 * offer id is authoritative; the SKU only fills in for lines Mercur reports
 * without one, since one seller order may carry a SKU twice.
 */
final class LineMatcher
{
    /**
     * @param array<int, array{id: string, offer_id?: string|null, sku?: string|null}> $remoteLines
     * @param array<string, array{offer_id: string, sku: string}> $localLines keyed by the caller's line key
     * @return array<string, string> local key => remote line id
     */
    public static function match(array $remoteLines, array $localLines): array
    {
        $unused = [];
        foreach ($remoteLines as $line) {
            $unused[(string) $line['id']] = $line;
        }

        $matched = [];
        foreach ($localLines as $key => $local) {
            $found = self::take($unused, 'offer_id', (string) $local['offer_id']);
            if ($found !== null) {
                $matched[(string) $key] = $found;
            }
        }
        foreach ($localLines as $key => $local) {
            if (isset($matched[(string) $key])) {
                continue;
            }
            $found = self::take($unused, 'sku', (string) $local['sku']);
            if ($found !== null) {
                $matched[(string) $key] = $found;
            }
        }

        return $matched;
    }

    /**
     * @param array<string, array<string, mixed>> $unused
     */
    private static function take(array &$unused, string $field, string $value): ?string
    {
        if ($value === '') {
            return null;
        }
        foreach ($unused as $id => $line) {
            if ((string) ($line[$field] ?? '') === $value) {
                unset($unused[$id]);

                return (string) $id;
            }
        }

        return null;
    }
}
