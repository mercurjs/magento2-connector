<?php

declare(strict_types=1);

namespace Mercur\Test\Unit\Connector;

use Mercur\Connector\Model\SalesRule\OperatorSubtotal;
use PHPUnit\Framework\TestCase;

final class OperatorSubtotalTest extends TestCase
{
    public function testSellerAndChildLinesAreLeftOut(): void
    {
        $totals = OperatorSubtotal::sum([
            ['offer' => false, 'row_total' => 50.0, 'base_row_total' => 40.0, 'discount_amount' => 5.0, 'base_discount_amount' => 4.0, 'row_total_incl_tax' => 59.5, 'base_row_total_incl_tax' => 47.6, 'qty' => 2.0, 'row_weight' => 3.0],
            ['offer' => true, 'row_total' => 500.0, 'base_row_total' => 400.0, 'row_total_incl_tax' => 595.0, 'base_row_total_incl_tax' => 476.0, 'qty' => 9.0, 'row_weight' => 30.0],
            ['offer' => false, 'parent' => true, 'row_total' => 0.0, 'base_row_total' => 0.0, 'qty' => 1.0],
            ['offer' => false, 'row_total' => 25.0, 'base_row_total' => 20.0, 'qty' => 1.0, 'row_weight' => 1.0],
        ]);

        self::assertSame([
            'subtotal' => 75.0,
            'base_subtotal' => 60.0,
            'subtotal_with_discount' => 70.0,
            'base_subtotal_with_discount' => 56.0,
            'subtotal_incl_tax' => 84.5,
            'base_subtotal_incl_tax' => 67.6,
            'base_subtotal_total_incl_tax' => 67.6,
            'total_qty' => 3.0,
            'weight' => 4.0,
        ], $totals);
    }

    public function testACartOfSellerLinesOnlyHasNothingLeft(): void
    {
        $totals = OperatorSubtotal::sum([['offer' => true, 'row_total' => 10.0, 'qty' => 1.0]]);

        self::assertSame(0.0, $totals['subtotal']);
        self::assertSame(0.0, $totals['subtotal_incl_tax']);
        self::assertSame(0.0, $totals['base_subtotal_incl_tax']);
        self::assertSame(0.0, $totals['total_qty']);
    }
}
