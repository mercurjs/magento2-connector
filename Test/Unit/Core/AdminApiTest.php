<?php

declare(strict_types=1);

namespace Mercur\Test\Unit\Core;

use Mercur\Core\Model\Api\AdminApi;
use Mercur\Core\Model\Api\Client;
use Mercur\Core\Model\Config;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class AdminApiTest extends TestCase
{
    private Client&MockObject $client;

    private AdminApi $api;

    protected function setUp(): void
    {
        $this->client = $this->createMock(Client::class);
        $this->client->method('adminHeaders')->willReturn(['Authorization' => 'Basic x']);
        $config = $this->createMock(Config::class);
        $config->method('getPageSize')->willReturn(100);

        $this->api = new AdminApi($this->client, $config);
    }

    public function testOffersAreListedWithStockLevelsUnderTheInventoryItemLink(): void
    {
        $this->client->expects(self::once())->method('get')
            ->with('/admin/offers', self::callback(static function (array $query): bool {
                self::assertStringContainsString('+inventory_items.location_levels.stocked_quantity', $query['fields']);
                self::assertStringContainsString('+inventory_items.location_levels.reserved_quantity', $query['fields']);
                self::assertStringNotContainsString('inventory_item.', $query['fields']);
                self::assertSame('true', $query['with_deleted']);
                self::assertSame(['$gte' => '2026-01-01T00:00:00Z'], $query['updated_at']);

                return true;
            }))
            ->willReturn(['offers' => []]);

        $this->api->listOffers('2026-01-01T00:00:00Z', 0);
    }

    public function testAnOrderGroupIsReadWithItsChildOrders(): void
    {
        $this->client->expects(self::once())->method('get')
            ->with('/admin/order-groups/og_1', ['fields' => 'id,display_id,orders.id'])
            ->willReturn(['order_group' => ['id' => 'og_1', 'orders' => [['id' => 'order_1']]]]);

        self::assertSame([['id' => 'order_1']], $this->api->getOrderGroup('og_1')['orders']);
    }

    public function testPublishedProductsAreListedWithVariantOptionValues(): void
    {
        $this->client->expects(self::once())->method('get')
            ->with('/admin/products', self::callback(static function (array $query): bool {
                self::assertSame(['published'], $query['status']);
                self::assertStringContainsString('+variants.options.value', $query['fields']);
                self::assertStringContainsString('+variants.options.option.title', $query['fields']);

                return true;
            }))
            ->willReturn(['products' => []]);

        $this->api->listPublishedProducts(null, 0);
    }
}
