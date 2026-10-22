<?php

declare(strict_types=1);

namespace Mercur\Test\Unit\Connector;

use Mercur\Connector\Model\Checkout\AddressMapper;
use PHPUnit\Framework\TestCase;

final class AddressMapperTest extends TestCase
{
    public function testMapsMagentoFieldsToMercurOnes(): void
    {
        $payload = AddressMapper::map([
            'firstname' => 'Ada',
            'lastname' => 'Lovelace',
            'company' => 'Analytical Engines',
            'street' => "12 Main St\nFloor 2",
            'city' => 'London',
            'region_code' => null,
            'region' => 'Greater London',
            'postcode' => 'N1',
            'country_id' => 'GB',
            'telephone' => '0123',
        ]);

        self::assertSame([
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'company' => 'Analytical Engines',
            'address_1' => '12 Main St',
            'address_2' => 'Floor 2',
            'city' => 'London',
            'province' => 'Greater London',
            'postal_code' => 'N1',
            'country_code' => 'gb',
            'phone' => '0123',
        ], $payload);
    }

    public function testStreetArrayAndRegionCodeArePreferred(): void
    {
        $payload = AddressMapper::map(['street' => ['A', 'B'], 'region_code' => 'BY', 'region' => 'Bavaria', 'country_id' => 'DE']);

        self::assertSame('A', $payload['address_1']);
        self::assertSame('B', $payload['address_2']);
        self::assertSame('BY', $payload['province']);
    }

    public function testEmailFallsThroughTheChain(): void
    {
        self::assertSame('c@x.test', AddressMapper::email('c@x.test', 's@x.test', 'b@x.test'));
        self::assertSame('s@x.test', AddressMapper::email('', 's@x.test', 'b@x.test'));
        self::assertSame('b@x.test', AddressMapper::email(null, ' ', 'b@x.test'));
        self::assertSame('', AddressMapper::email(null, null));
    }
}
