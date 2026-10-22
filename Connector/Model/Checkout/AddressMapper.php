<?php

declare(strict_types=1);

namespace Mercur\Connector\Model\Checkout;

/**
 * Turns a Magento quote address into the Mercur address payload.
 */
final class AddressMapper
{
    /**
     * @param array<string, mixed> $address Magento address data (street as array or newline-separated string)
     * @return array<string, string>
     */
    public static function map(array $address): array
    {
        $street = $address['street'] ?? [];
        if (is_string($street)) {
            $street = preg_split('/\r?\n/', $street) ?: [];
        }
        $street = array_values($street);

        return [
            'first_name' => (string) ($address['firstname'] ?? ''),
            'last_name' => (string) ($address['lastname'] ?? ''),
            'company' => (string) ($address['company'] ?? ''),
            'address_1' => (string) ($street[0] ?? ''),
            'address_2' => (string) ($street[1] ?? ''),
            'city' => (string) ($address['city'] ?? ''),
            'province' => (string) (($address['region_code'] ?? '') ?: ($address['region'] ?? '')),
            'postal_code' => (string) ($address['postcode'] ?? ''),
            'country_code' => strtolower((string) ($address['country_id'] ?? '')),
            'phone' => (string) ($address['telephone'] ?? ''),
        ];
    }

    public static function email(?string ...$candidates): string
    {
        foreach ($candidates as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate !== '') {
                return $candidate;
            }
        }

        return '';
    }
}
