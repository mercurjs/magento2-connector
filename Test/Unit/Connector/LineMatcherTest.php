<?php

declare(strict_types=1);

namespace Mercur\Test\Unit\Connector;

use Mercur\Connector\Model\Order\LineMatcher;
use PHPUnit\Framework\TestCase;

final class LineMatcherTest extends TestCase
{
    public function testOfferIdWinsOverSkuWhenTheSkuRepeats(): void
    {
        $remote = [
            ['id' => 'ordli_1', 'offer_id' => 'off_b', 'sku' => 'SKU-1'],
            ['id' => 'ordli_2', 'offer_id' => 'off_a', 'sku' => 'SKU-1'],
        ];
        $local = [
            '10' => ['offer_id' => 'off_a', 'sku' => 'SKU-1'],
            '11' => ['offer_id' => 'off_b', 'sku' => 'SKU-1'],
        ];

        self::assertSame(['10' => 'ordli_2', '11' => 'ordli_1'], LineMatcher::match($remote, $local));
    }

    public function testSkuFillsInWhenMercurReportsNoOffer(): void
    {
        $remote = [
            ['id' => 'ordli_1', 'offer_id' => null, 'sku' => 'SKU-1'],
            ['id' => 'ordli_2', 'offer_id' => 'off_b', 'sku' => 'SKU-2'],
        ];
        $local = [
            '10' => ['offer_id' => 'off_a', 'sku' => 'SKU-1'],
            '11' => ['offer_id' => 'off_b', 'sku' => 'SKU-2'],
            '12' => ['offer_id' => 'off_c', 'sku' => 'SKU-3'],
        ];

        self::assertSame(['11' => 'ordli_2', '10' => 'ordli_1'], LineMatcher::match($remote, $local));
    }

    public function testEachRemoteLineIsUsedOnce(): void
    {
        $remote = [['id' => 'ordli_1', 'offer_id' => '', 'sku' => 'SKU-1']];
        $local = [
            '10' => ['offer_id' => 'off_a', 'sku' => 'SKU-1'],
            '11' => ['offer_id' => 'off_b', 'sku' => 'SKU-1'],
        ];

        self::assertSame(['10' => 'ordli_1'], LineMatcher::match($remote, $local));
    }
}
