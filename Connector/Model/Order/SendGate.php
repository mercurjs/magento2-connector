<?php

declare(strict_types=1);

namespace Mercur\Connector\Model\Order;

/**
 * Decides whether a Magento order is ready to be created in Mercur.
 */
final class SendGate
{
    /**
     * @param string[] $allowedStatuses
     */
    public static function shouldSend(string $status, array $allowedStatuses, bool $alreadySent, bool $hasCart): bool
    {
        if ($alreadySent || !$hasCart) {
            return false;
        }

        return in_array($status, $allowedStatuses, true);
    }
}
