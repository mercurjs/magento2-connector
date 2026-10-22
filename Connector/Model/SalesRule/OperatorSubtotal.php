<?php

declare(strict_types=1);

namespace Mercur\Connector\Model\SalesRule;

/**
 * Address figures with the seller lines taken out, so that cart rule
 * conditions on subtotal, quantity or weight only see what the operator sells.
 */
final class OperatorSubtotal
{
    /**
     * @param array<int, array{offer: bool, parent?: bool, row_total?: float, base_row_total?: float,
     *   discount_amount?: float, base_discount_amount?: float, row_total_incl_tax?: float,
     *   base_row_total_incl_tax?: float, qty?: float, row_weight?: float}> $rows
     * @return array<string, float>
     */
    public static function sum(array $rows): array
    {
        $totals = [
            'subtotal' => 0.0,
            'base_subtotal' => 0.0,
            'subtotal_with_discount' => 0.0,
            'base_subtotal_with_discount' => 0.0,
            'subtotal_incl_tax' => 0.0,
            'base_subtotal_incl_tax' => 0.0,
            'base_subtotal_total_incl_tax' => 0.0,
            'total_qty' => 0.0,
            'weight' => 0.0,
        ];

        foreach ($rows as $row) {
            if (($row['offer'] ?? false) || ($row['parent'] ?? false)) {
                continue;
            }
            $rowTotal = (float) ($row['row_total'] ?? 0);
            $baseRowTotal = (float) ($row['base_row_total'] ?? 0);
            $totals['subtotal'] += $rowTotal;
            $totals['base_subtotal'] += $baseRowTotal;
            $totals['subtotal_with_discount'] += $rowTotal - (float) ($row['discount_amount'] ?? 0);
            $totals['base_subtotal_with_discount'] += $baseRowTotal - (float) ($row['base_discount_amount'] ?? 0);
            $baseRowTotalInclTax = (float) ($row['base_row_total_incl_tax'] ?? $baseRowTotal);
            $totals['subtotal_incl_tax'] += (float) ($row['row_total_incl_tax'] ?? $rowTotal);
            $totals['base_subtotal_incl_tax'] += $baseRowTotalInclTax;
            $totals['base_subtotal_total_incl_tax'] += $baseRowTotalInclTax;
            $totals['total_qty'] += (float) ($row['qty'] ?? 0);
            $totals['weight'] += (float) ($row['row_weight'] ?? 0);
        }

        return $totals;
    }
}
