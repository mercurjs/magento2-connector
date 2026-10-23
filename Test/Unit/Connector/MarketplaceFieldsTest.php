<?php

declare(strict_types=1);

namespace Mercur\Test\Unit\Connector;

use Magento\Framework\DataObject;
use Mercur\Connector\Model\Order\MarketplaceFields;
use PHPUnit\Framework\TestCase;

final class MarketplaceFieldsTest extends TestCase
{
    public function testCopiesOnlyTheListedFields(): void
    {
        $quoteItem = new DataObject([
            'mercur_offer_id' => 'offer_1',
            'mercur_seller_id' => 'sel_1',
            'mercur_line_item_id' => 'cali_1',
            'sku' => 'IGNORED',
        ]);
        $orderItem = new DataObject(['sku' => 'KEPT']);

        MarketplaceFields::copy($quoteItem, $orderItem, MarketplaceFields::ITEM);

        self::assertSame('offer_1', $orderItem->getData('mercur_offer_id'));
        self::assertSame('sel_1', $orderItem->getData('mercur_seller_id'));
        self::assertSame('cali_1', $orderItem->getData('mercur_line_item_id'));
        self::assertSame('KEPT', $orderItem->getData('sku'));
    }

    public function testAMissingValueLeavesTheTargetAlone(): void
    {
        $order = new DataObject(['mercur_cart_id' => 'cart_old']);

        MarketplaceFields::copy(new DataObject(['mercur_shipping_methods' => '{}']), $order, MarketplaceFields::ORDER);

        self::assertSame('cart_old', $order->getData('mercur_cart_id'));
        self::assertSame('{}', $order->getData('mercur_shipping_methods'));
    }
}
