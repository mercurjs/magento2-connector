<?php

declare(strict_types=1);

namespace Mercur\Test\Unit\Connector;

use Mercur\Connector\Model\Fulfillment\ShipmentPlanner;
use Mercur\Connector\Model\Fulfillment\StatusResolver;
use PHPUnit\Framework\TestCase;

final class FulfillmentTest extends TestCase
{
    public function testStatusFollowsTheFurthestStepForTheWholeLine(): void
    {
        $resolver = new StatusResolver();

        self::assertSame('not_fulfilled', $resolver->resolve(['quantity' => 2]));
        self::assertSame('fulfilled', $resolver->resolve(['quantity' => 2, 'detail' => ['fulfilled_quantity' => 2]]));
        self::assertSame('partially_shipped', $resolver->resolve(['quantity' => 2, 'detail' => ['shipped_quantity' => 1]]));
        self::assertSame('shipped', $resolver->resolve(['quantity' => 2, 'detail' => ['shipped_quantity' => 2]]));
        self::assertSame('delivered', $resolver->resolve(['quantity' => 2, 'shipped_quantity' => 2, 'delivered_quantity' => 2]));
        self::assertSame('return_requested', $resolver->resolve(['quantity' => 2, 'detail' => ['delivered_quantity' => 2, 'return_requested_quantity' => 1]]));
        self::assertSame('returned', $resolver->resolve(['quantity' => 2, 'detail' => ['return_received_quantity' => 1]]));
    }

    public function testASellerCancelingItsOwnOrderIsARefusal(): void
    {
        self::assertSame('refused', StatusResolver::cancelStatus('sel_1', 'sel_1'));
        self::assertSame('canceled', StatusResolver::cancelStatus('user_1', 'sel_1'));
        self::assertSame('canceled', StatusResolver::cancelStatus(null, 'sel_1'));
        self::assertSame('canceled', StatusResolver::cancelStatus('', ''));
        self::assertTrue(StatusResolver::isCanceled('refused'));
        self::assertTrue(StatusResolver::isCanceled('canceled'));
        self::assertFalse(StatusResolver::isCanceled('shipped'));
    }

    public function testOneShipmentPerShippedFulfillmentWithOnlyItsLines(): void
    {
        $order = ['fulfillments' => [
            [
                'id' => 'ful_1',
                'shipped_at' => '2026-10-21T10:00:00Z',
                'provider_id' => 'manual_manual',
                'items' => [['line_item_id' => 'ordli_a', 'quantity' => 1]],
                'labels' => [['tracking_number' => 'TRK1', 'tracking_url' => 'https://t.example/TRK1']],
            ],
            [
                'id' => 'ful_2',
                'shipped_at' => '2026-10-22T10:00:00Z',
                'items' => [['line_item_id' => 'ordli_a', 'quantity' => 1], ['line_item_id' => 'ordli_b', 'quantity' => 3]],
            ],
            ['id' => 'ful_3', 'shipped_at' => null, 'items' => [['line_item_id' => 'ordli_b', 'quantity' => 1]]],
            ['id' => 'ful_4', 'shipped_at' => '2026-10-22T10:00:00Z', 'canceled_at' => '2026-10-22T11:00:00Z', 'items' => [['line_item_id' => 'ordli_b', 'quantity' => 1]]],
        ]];

        $plans = (new ShipmentPlanner())->plan($order, ['ordli_a' => 10, 'ordli_b' => 11], [10 => 2.0, 11 => 3.0], []);

        self::assertCount(2, $plans);
        self::assertSame('ful_1', $plans[0]['fulfillment_id']);
        self::assertSame([10 => 1.0], $plans[0]['items']);
        self::assertSame([['number' => 'TRK1', 'url' => 'https://t.example/TRK1', 'carrier' => 'manual_manual']], $plans[0]['tracks']);
        self::assertSame([10 => 1.0, 11 => 3.0], $plans[1]['items']);
    }

    public function testAlreadyShippedFulfillmentsAndOvershipmentAreSkipped(): void
    {
        $order = ['fulfillments' => [
            ['id' => 'ful_1', 'shipped_at' => 'x', 'items' => [['line_item_id' => 'ordli_a', 'quantity' => 1]]],
            ['id' => 'ful_2', 'shipped_at' => 'x', 'items' => [['line_item_id' => 'ordli_a', 'quantity' => 5]]],
        ]];

        $plans = (new ShipmentPlanner())->plan($order, ['ordli_a' => 10], [10 => 2.0], ['ful_1']);

        self::assertSame([['fulfillment_id' => 'ful_2', 'items' => [10 => 2.0], 'tracks' => []]], $plans);
    }
}
