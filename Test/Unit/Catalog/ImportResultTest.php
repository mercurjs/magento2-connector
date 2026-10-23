<?php

declare(strict_types=1);

namespace Mercur\Test\Unit\Catalog;

use Mercur\Catalog\Model\Product\ImportResult;
use PHPUnit\Framework\TestCase;

final class ImportResultTest extends TestCase
{
    public function testWarningsCarryTheProductLabelAndNotesDoNot(): void
    {
        $result = new ImportResult('Vantage 204 Runner (prod_1)');
        $result->note('Created attribute "size" (Size) in attribute group "Marketplace" for the Mercur variant axis');
        $result->warn('variant VR-43 has no value for "size"; no configurable parent built');

        self::assertSame([
            'Created attribute "size" (Size) in attribute group "Marketplace" for the Mercur variant axis',
            'Vantage 204 Runner (prod_1): variant VR-43 has no value for "size"; no configurable parent built',
        ], $result->lines());
    }

    public function testCountsVariantsAndParentsSeparately(): void
    {
        $result = new ImportResult('P (prod_1)');
        $result->addSku('VR-42');
        $result->addSku('VR-43');
        $result->addSku('mercur-p', true);

        self::assertSame(['VR-42', 'VR-43', 'mercur-p'], $result->skus());
        self::assertSame(2, $result->variantCount());
        self::assertSame(1, $result->parentCount());
    }
}
