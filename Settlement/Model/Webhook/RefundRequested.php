<?php

declare(strict_types=1);

namespace Mercur\Settlement\Model\Webhook;

use Mercur\Connector\Model\Webhook\HandlerInterface;
use Mercur\Settlement\Model\RefundExecutor;

/**
 * order.refund_requested: `{order_id, refund_id, amount, note}`.
 */
class RefundRequested implements HandlerInterface
{
    public function __construct(private readonly RefundExecutor $executor)
    {
    }

    public function handle(string $event, array $data): void
    {
        $this->executor->execute(
            (string) ($data['order_id'] ?? ''),
            (string) ($data['refund_id'] ?? ''),
            (float) ($data['amount'] ?? 0),
            isset($data['note']) ? (string) $data['note'] : null
        );
    }
}
