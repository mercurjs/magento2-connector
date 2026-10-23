<?php

declare(strict_types=1);

namespace Mercur\Test\Unit\Core;

use Mercur\Core\Model\Api\Client;
use Mercur\Core\Model\Api\StoreApi;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class StoreApiTest extends TestCase
{
    private Client&MockObject $client;

    protected function setUp(): void
    {
        $this->client = $this->createMock(Client::class);
        $this->client->method('storeHeaders')->willReturn(['x-publishable-api-key' => 'pk']);
    }

    public function testTheCartIsCreatedInTheLowerCasedCurrency(): void
    {
        $this->client->expects(self::once())->method('post')
            ->with('/store/carts', [
                'region_id' => 'reg_1',
                'sales_channel_id' => 'sc_1',
                'currency_code' => 'eur',
                'metadata' => ['channel' => 'magento'],
            ])
            ->willReturn(['cart' => ['id' => 'cart_1']]);

        self::assertSame(['id' => 'cart_1'], (new StoreApi($this->client))->createCart('reg_1', 'sc_1', 'EUR', ['channel' => 'magento']));
    }

    public function testAnUnknownCurrencyIsLeftToTheRegion(): void
    {
        $this->client->expects(self::once())->method('post')
            ->with('/store/carts', ['region_id' => 'reg_1', 'sales_channel_id' => 'sc_1'])
            ->willReturn(['cart' => ['id' => 'cart_1']]);

        (new StoreApi($this->client))->createCart('reg_1', 'sc_1', '', []);
    }
}
