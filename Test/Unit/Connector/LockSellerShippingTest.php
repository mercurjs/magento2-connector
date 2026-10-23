<?php

declare(strict_types=1);

namespace Mercur\Test\Unit\Connector;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Sales\Model\Order\Item;
use Magento\Store\Model\StoreManagerInterface;
use Mercur\Connector\Model\Order\ShipmentLock;
use Mercur\Connector\Plugin\Sales\LockSellerShipping;
use Mercur\Core\Model\Config;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class LockSellerShippingTest extends TestCase
{
    public function testASellerLineIsLocked(): void
    {
        self::assertSame(1, $this->plugin(true)->afterGetLockedDoShip($this->item('offer_1'), null));
    }

    public function testAnOperatorLineKeepsItsOwnFlag(): void
    {
        self::assertNull($this->plugin(true)->afterGetLockedDoShip($this->item(null), null));
        self::assertSame(0, $this->plugin(true)->afterGetLockedDoShip($this->item(null), 0));
    }

    public function testAnAlreadyLockedLineIsLeftAlone(): void
    {
        self::assertSame(1, $this->plugin(false)->afterGetLockedDoShip($this->item(null), 1));
    }

    public function testAChildFollowsItsConfigurableParent(): void
    {
        $parent = $this->item('offer_1');
        $child = $this->item(null, $parent);

        self::assertSame(1, $this->plugin(true)->afterGetLockedDoShip($child, null));
    }

    public function testTheToggleTurnsTheLockOff(): void
    {
        self::assertNull($this->plugin(false)->afterGetLockedDoShip($this->item('offer_1'), null));
    }

    public function testTheConnectorsOwnShipmentsPass(): void
    {
        $lock = new ShipmentLock();
        $plugin = $this->plugin(true, $lock);
        $item = $this->item('offer_1');

        $lock->lift(static function () use ($plugin, $item): void {
            self::assertNull($plugin->afterGetLockedDoShip($item, null));
        });
    }

    private function plugin(bool $enabled, ?ShipmentLock $lock = null): LockSellerShipping
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('isSetFlag')->with('mercur/order/lock_seller_shipping')->willReturn($enabled);

        return new LockSellerShipping($lock ?? new ShipmentLock(), new Config($scopeConfig, $this->createMock(StoreManagerInterface::class)));
    }

    private function item(?string $offerId, ?Item $parent = null): Item&MockObject
    {
        $item = $this->createMock(Item::class);
        $item->method('getParentItem')->willReturn($parent);
        $item->method('getStoreId')->willReturn(1);
        $item->method('getData')->willReturnCallback(static fn (string $key) => $key === 'mercur_offer_id' ? $offerId : null);

        return $item;
    }
}
