<?php

declare(strict_types=1);

namespace Mercur\Test\Unit\Connector;

use Mercur\Connector\Model\Sync\OfferMapper;
use Mercur\Connector\Model\Sync\SellerMapper;
use PHPUnit\Framework\TestCase;

final class OfferMapperTest extends TestCase
{
    private function offer(array $overrides = []): array
    {
        $base = [
            'id' => 'offer_1',
            'sku' => 'SELLER-SKU',
            'seller_id' => 'sel_1',
            'variant_id' => 'variant_1',
            'product_id' => 'prod_1',
            'shipping_profile_id' => 'sp_1',
            'leadtime_to_ship' => null,
            'updated_at' => '2026-10-21T10:00:00.000Z',
            'product_variant' => ['sku' => 'MAG-SKU', 'product_id' => 'prod_1'],
            'seller' => ['status' => 'open', 'default_leadtime_to_ship' => 3],
            'condition' => ['code' => 'new'],
            'prices' => [
                ['amount' => 25, 'currency_code' => 'usd', 'min_quantity' => null, 'max_quantity' => null],
                ['amount' => 20, 'currency_code' => 'usd', 'min_quantity' => 10, 'max_quantity' => null],
                ['amount' => 22, 'currency_code' => 'eur', 'min_quantity' => null, 'max_quantity' => null],
            ],
            'inventory_items' => [
                ['required_quantity' => 1, 'inventory_item' => ['location_levels' => [
                    ['stocked_quantity' => 10, 'reserved_quantity' => 2],
                    ['stocked_quantity' => 5, 'reserved_quantity' => 0],
                ]]],
            ],
        ];
        if (isset($overrides['seller'])) {
            $overrides['seller'] = array_replace($base['seller'], $overrides['seller']);
        }

        return array_replace($base, $overrides);
    }

    public function testMapsStockAndSellerLeadTime(): void
    {
        $row = (new OfferMapper(new SellerMapper()))->map($this->offer());

        self::assertSame('MAG-SKU', $row['product_sku']);
        self::assertArrayNotHasKey('price', $row);
        self::assertSame(13, $row['quantity']);
        self::assertSame(3, $row['leadtime_to_ship']);
        self::assertSame(1, $row['active']);
        self::assertSame('2026-10-21 10:00:00', $row['updated_at']);
    }

    public function testKitQuantityIsLimitedByTheScarcestComponent(): void
    {
        $row = (new OfferMapper(new SellerMapper()))->map($this->offer([
            'inventory_items' => [
                ['required_quantity' => 2, 'inventory_item' => ['location_levels' => [['stocked_quantity' => 9, 'reserved_quantity' => 0]]]],
                ['required_quantity' => 1, 'inventory_item' => ['location_levels' => [['stocked_quantity' => 100, 'reserved_quantity' => 0]]]],
            ],
        ]));

        self::assertSame(4, $row['quantity']);
    }

    public function testSuspendedOrClosedSellerDeactivatesTheOffer(): void
    {
        $mapper = new OfferMapper(new SellerMapper());

        self::assertSame(0, $mapper->map($this->offer(['seller' => ['status' => 'suspended']]))['active']);
        self::assertSame(0, $mapper->map($this->offer(['seller' => [
            'status' => 'open',
            'closed_from' => '2000-01-01T00:00:00Z',
            'closed_to' => '2999-01-01T00:00:00Z',
        ]]))['active']);
    }

    public function testOfferWithoutVariantSkuIsSkipped(): void
    {
        self::assertNull((new OfferMapper(new SellerMapper()))->map($this->offer(['product_variant' => ['sku' => null]])));
    }
}
