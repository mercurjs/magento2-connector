<?php

declare(strict_types=1);

namespace Mercur\Test\Unit\Frontend;

use Magento\Catalog\Block\Product\ListProduct;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Framework\DataObject;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Mercur\Frontend\Model\ListingSummary;
use Mercur\Frontend\Plugin\PrimeListingSummary;
use PHPUnit\Framework\TestCase;

final class PrimeListingSummaryTest extends TestCase
{
    public function testPrimesSummariesForTheWebsiteOfTheCollectionStore(): void
    {
        $collection = $this->createMock(Collection::class);
        $collection->method('isLoaded')->willReturn(true);
        $collection->method('getStoreId')->willReturn(2);
        $collection->method('getIterator')->willReturn(new \ArrayIterator([
            new DataObject(['sku' => 'SKU-1']),
            new DataObject(['sku' => 'SKU-2']),
        ]));

        $store = $this->createMock(Store::class);
        $store->method('getWebsiteId')->willReturn(3);
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->with(2)->willReturn($store);

        $summaries = $this->createMock(ListingSummary::class);
        $summaries->expects(self::once())->method('prime')->with(['SKU-1', 'SKU-2'], 3);

        $plugin = new PrimeListingSummary($summaries, $storeManager);

        self::assertSame(
            $collection,
            $plugin->afterGetLoadedProductCollection($this->createMock(ListProduct::class), $collection)
        );
    }

    public function testSkipsCollectionsThatAreNotLoaded(): void
    {
        $collection = $this->createMock(Collection::class);
        $collection->method('isLoaded')->willReturn(false);

        $summaries = $this->createMock(ListingSummary::class);
        $summaries->expects(self::never())->method('prime');

        $plugin = new PrimeListingSummary($summaries, $this->createMock(StoreManagerInterface::class));
        $plugin->afterGetLoadedProductCollection($this->createMock(ListProduct::class), $collection);
    }
}
