<?php

declare(strict_types=1);

namespace Mercur\Test\Unit\Connector;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\StoreManagerInterface;
use Mercur\Connector\Model\Catalog\ProductMatcher;
use Mercur\Connector\Model\ResourceModel\Product\IdentifierLookup;
use Mercur\Core\Model\Config;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ProductMatcherTest extends TestCase
{
    private IdentifierLookup&MockObject $lookup;

    protected function setUp(): void
    {
        $this->lookup = $this->createMock(IdentifierLookup::class);
    }

    public function testAKnownSkuWinsOverAnIdentifierMatch(): void
    {
        $this->lookup->method('existingSkus')->willReturn(['SELLER-1']);
        $this->lookup->method('skusByIdentifier')->willReturn(['4006381333931' => 'OPERATOR-1']);

        self::assertSame('SELLER-1', $this->matcher(['ean'])->resolve('SELLER-1', ['4006381333931']));
    }

    public function testAnUnknownSkuFallsBackToTheIdentifier(): void
    {
        $this->lookup->method('existingSkus')->willReturn([]);
        $this->lookup->method('skusByIdentifier')->willReturn(['4006381333931' => 'OPERATOR-1']);

        self::assertSame('OPERATOR-1', $this->matcher(['ean'])->resolve('SELLER-1', ['4006381333931']));
        self::assertSame('OPERATOR-1', $this->matcher(['ean'])->resolve(null, ['4006381333931']));
    }

    public function testNothingMatchesWithoutAnIdentifierHit(): void
    {
        $this->lookup->method('existingSkus')->willReturn([]);
        $this->lookup->method('skusByIdentifier')->willReturn([]);

        self::assertNull($this->matcher(['ean'])->resolve('SELLER-1', ['4006381333931']));
    }

    public function testIdentifiersAreNotQueriedWithoutConfiguredAttributes(): void
    {
        $this->lookup->method('existingSkus')->willReturn([]);
        $this->lookup->expects(self::never())->method('skusByIdentifier');

        self::assertNull($this->matcher([])->resolve('SELLER-1', ['4006381333931']));
    }

    public function testAPrimedPageIsResolvedWithTwoQueries(): void
    {
        $this->lookup->expects(self::once())->method('existingSkus')->with(['SELLER-1', 'SELLER-2'])->willReturn(['SELLER-2']);
        $this->lookup->expects(self::once())->method('skusByIdentifier')
            ->with(['ean'], ['4006381333931', '04006381333931', '12345678905', '012345678905', '0012345678905', '00012345678905'])
            ->willReturn(['12345678905' => 'OPERATOR-1']);

        $matcher = $this->matcher(['ean']);
        $matcher->prime([
            ['sku' => 'SELLER-1', 'identifiers' => ['4006381333931']],
            ['sku' => 'SELLER-2', 'identifiers' => ['12345678905']],
        ]);

        self::assertNull($matcher->resolve('SELLER-1', ['4006381333931']));
        self::assertSame('SELLER-2', $matcher->resolve('SELLER-2', ['12345678905']));
    }

    /**
     * @param string[] $identifierAttributes
     */
    private function matcher(array $identifierAttributes): ProductMatcher
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->with('mercur/offers/identifier_attributes')->willReturn(implode(',', $identifierAttributes));

        return new ProductMatcher($this->lookup, new Config($scopeConfig, $this->createMock(StoreManagerInterface::class)));
    }
}
