<?php

declare(strict_types=1);

namespace Mercur\Connector\Model\ResourceModel\Webhook;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;

class EventLog
{
    public const TABLE = 'mercur_webhook_event';

    public function __construct(private readonly ResourceConnection $resource)
    {
    }

    /**
     * @return bool false when the event id was already recorded
     */
    public function insertIgnore(string $eventId, string $event): bool
    {
        $connection = $this->resource->getConnection();
        $inserted = $connection->insertArray(
            $this->table(),
            ['event_id', 'event', 'received_at'],
            [[$eventId, $event, gmdate('Y-m-d H:i:s')]],
            AdapterInterface::INSERT_IGNORE
        );

        return $inserted === 1;
    }

    public function delete(string $eventId): void
    {
        $this->resource->getConnection()->delete($this->table(), ['event_id = ?' => $eventId]);
    }

    public function deleteReceivedBefore(string $cutoff): int
    {
        return $this->resource->getConnection()->delete($this->table(), ['received_at < ?' => $cutoff]);
    }

    private function table(): string
    {
        return $this->resource->getTableName(self::TABLE);
    }
}
