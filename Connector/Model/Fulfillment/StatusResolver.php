<?php

declare(strict_types=1);

namespace Mercur\Connector\Model\Fulfillment;

/**
 * Marketplace status of one line from the quantities on its Mercur seller order line.
 */
final class StatusResolver
{
    public const NOT_FULFILLED = 'not_fulfilled';
    public const FULFILLED = 'fulfilled';
    public const PARTIALLY_SHIPPED = 'partially_shipped';
    public const SHIPPED = 'shipped';
    public const DELIVERED = 'delivered';
    public const RETURN_REQUESTED = 'return_requested';
    public const RETURNED = 'returned';
    public const CANCELED = 'canceled';

    public function resolve(array $line): string
    {
        $qty = $this->qty($line, 'quantity');
        if ($qty <= 0) {
            return self::NOT_FULFILLED;
        }

        return match (true) {
            $this->qty($line, 'return_received_quantity') > 0 => self::RETURNED,
            $this->qty($line, 'return_requested_quantity') > 0 => self::RETURN_REQUESTED,
            $this->qty($line, 'delivered_quantity') >= $qty => self::DELIVERED,
            $this->qty($line, 'shipped_quantity') >= $qty => self::SHIPPED,
            $this->qty($line, 'shipped_quantity') > 0 => self::PARTIALLY_SHIPPED,
            $this->qty($line, 'fulfilled_quantity') > 0 => self::FULFILLED,
            default => self::NOT_FULFILLED,
        };
    }

    /**
     * Medusa reports these quantities on the line detail; older responses also flatten them.
     */
    private function qty(array $line, string $key): float
    {
        return (float) ($line['detail'][$key] ?? $line[$key] ?? 0);
    }
}
