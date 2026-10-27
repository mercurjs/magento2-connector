<?php

declare(strict_types=1);

namespace Mercur\Test\Unit\OfferIndexer;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\Indexer\CacheContext;
use Magento\Framework\Indexer\CacheContextFactory;
use Mercur\OfferIndexer\Model\ProductCacheCleaner;
use PHPUnit\Framework\TestCase;

class ProductCacheCleanerTest extends TestCase
{
    /** @var array<int, array{string, array<string, mixed>}> */
    private array $dispatched = [];

    /** @var string[][] */
    private array $cleaned = [];

    private function cleaner(): ProductCacheCleaner
    {
        $factory = $this->createMock(CacheContextFactory::class);
        $factory->method('create')->willReturnCallback(static fn () => new CacheContext());

        $events = $this->createMock(EventManager::class);
        $events->method('dispatch')->willReturnCallback(function (string $name, array $data = []): void {
            $this->dispatched[] = [$name, $data];
        });

        $cache = $this->createMock(CacheInterface::class);
        $cache->method('clean')->willReturnCallback(function (array $tags = []): bool {
            $this->cleaned[] = $tags;
            return true;
        });

        return new ProductCacheCleaner($factory, $events, $cache);
    }

    public function testEvictsEachProductOnce(): void
    {
        $this->cleaner()->cleanProducts([350, 351, '350']);

        self::assertCount(1, $this->dispatched);
        [$name, $data] = $this->dispatched[0];
        self::assertSame('clean_cache_by_tags', $name);
        self::assertSame(['cat_p_350', 'cat_p_351'], $data['object']->getIdentities());
        self::assertSame([['cat_p_350', 'cat_p_351']], $this->cleaned);
    }

    public function testNothingToEvict(): void
    {
        $this->cleaner()->cleanProducts([]);

        self::assertSame([], $this->dispatched);
        self::assertSame([], $this->cleaned);
    }

    public function testFullReindexEvictsEveryProductPage(): void
    {
        $this->cleaner()->cleanAll();

        self::assertSame(['cat_p'], $this->dispatched[0][1]['object']->getIdentities());
        self::assertSame([['cat_p']], $this->cleaned);
    }
}
