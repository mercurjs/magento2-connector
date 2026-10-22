<?php

declare(strict_types=1);

namespace Mercur\Settlement\Model\Task;

use Magento\Framework\App\ResourceConnection;
use Mercur\Core\Model\Api\AdminApi;
use Mercur\Process\Api\TaskInterface;
use Mercur\Process\Model\Process;
use Mercur\Settlement\Model\RefundExecutor;

/**
 * Catches Mercur refunds whose webhook never arrived: reads the payments of
 * recent seller orders and executes every refund that did not come from
 * Magento and has no credit memo yet.
 */
class RefundPoll implements TaskInterface
{
    public const CODE = 'refunds';
    private const LOOKBACK_DAYS = 30;
    private const BATCH = 100;

    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly AdminApi $api,
        private readonly RefundExecutor $executor
    ) {
    }

    public function getCode(): string
    {
        return self::CODE;
    }

    public function getName(): string
    {
        return 'Execute refunds started in Mercur';
    }

    public function execute(Process $process, array $params): void
    {
        $executed = 0;
        foreach ($this->recentSellerOrders() as $sellerOrderId) {
            try {
                $remote = $this->api->getOrder(
                    $sellerOrderId,
                    'id,payment_collections.payments.provider_id,payment_collections.payments.refunds.*'
                );
            } catch (\Throwable $e) {
                $process->output(sprintf('Skipped %s: %s', $sellerOrderId, $e->getMessage()));
                continue;
            }
            foreach ($remote['payment_collections'] ?? [] as $collection) {
                foreach ($collection['payments'] ?? [] as $payment) {
                    foreach ($payment['refunds'] ?? [] as $refund) {
                        if (($refund['metadata']['source'] ?? null) === 'external') {
                            continue;
                        }
                        if ($this->executor->execute($sellerOrderId, (string) $refund['id'], (float) $refund['amount'], $refund['note'] ?? null)) {
                            $executed++;
                        }
                    }
                }
            }
        }
        $process->output("Executed $executed refund(s)");
    }

    /**
     * @return string[]
     */
    private function recentSellerOrders(): array
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()->distinct()
            ->from(['item' => $this->resource->getTableName('sales_order_item')], ['mercur_order_id'])
            ->join(['o' => $this->resource->getTableName('sales_order')], 'o.entity_id = item.order_id', [])
            ->where('item.mercur_order_id IS NOT NULL')
            ->where('o.state NOT IN (?)', ['closed', 'canceled'])
            ->where('o.updated_at >= ?', gmdate('Y-m-d H:i:s', time() - self::LOOKBACK_DAYS * 86400))
            ->limit(self::BATCH);

        return array_map('strval', $connection->fetchCol($select));
    }
}
