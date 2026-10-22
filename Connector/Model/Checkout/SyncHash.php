<?php

declare(strict_types=1);

namespace Mercur\Connector\Model\Checkout;

/**
 * Fingerprint of everything that influences the Mercur shipping quote. While
 * it is unchanged, totals collection reuses the last quote instead of calling
 * the API again.
 */
final class SyncHash
{
    /**
     * @param array<string, int> $lines offer id => quantity
     * @param array<string, string> $address
     * @param array<string, string> $chosen seller id => option id
     */
    public static function build(array $lines, array $address, array $chosen): string
    {
        return sha1((string) json_encode([
            self::canonical($lines),
            self::canonical($address),
            self::canonical($chosen),
        ]));
    }

    private static function canonical(array $data): array
    {
        ksort($data);
        foreach ($data as $key => $value) {
            $data[$key] = is_array($value) ? self::canonical($value) : (string) $value;
        }

        return $data;
    }
}
