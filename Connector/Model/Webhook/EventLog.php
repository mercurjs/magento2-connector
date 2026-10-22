<?php

declare(strict_types=1);

namespace Mercur\Connector\Model\Webhook;

use Mercur\Connector\Model\ResourceModel\Webhook\EventLog as EventLogResource;
use Mercur\Process\Api\HistoryPrunerInterface;

/**
 * Remembers delivered webhook ids so Mercur's retries never run a handler twice.
 */
class EventLog implements HistoryPrunerInterface
{
    public const RETENTION_DAYS = 30;

    public function __construct(private readonly EventLogResource $resource)
    {
    }

    /**
     * The delivery header is Mercur's retry-stable id; the payload id is the
     * next best thing and a body digest the last resort.
     */
    public static function eventId(?string $deliveryHeader, array $payload, string $body): string
    {
        $header = trim((string) $deliveryHeader);
        if ($header !== '') {
            return substr($header, 0, 64);
        }
        $id = $payload['id'] ?? null;
        if (is_string($id) && trim($id) !== '') {
            return substr(trim($id), 0, 64);
        }

        return sha1($body);
    }

    public static function isStale(array $payload, int $maxAgeSec, ?int $now = null): bool
    {
        if ($maxAgeSec <= 0) {
            return false;
        }
        $createdAt = $payload['created_at'] ?? null;
        if (!is_string($createdAt) || $createdAt === '') {
            return false;
        }
        $stamp = strtotime($createdAt);
        if ($stamp === false) {
            return false;
        }

        return ($now ?? time()) - $stamp > $maxAgeSec;
    }

    /**
     * @return bool false when the id was seen before
     */
    public function markReceived(string $eventId, string $event): bool
    {
        return $this->resource->insertIgnore($eventId, $event);
    }

    public function forget(string $eventId): void
    {
        $this->resource->delete($eventId);
    }

    public function prune(): int
    {
        $cutoff = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
            ->modify('-' . self::RETENTION_DAYS . ' days')
            ->format('Y-m-d H:i:s');

        return $this->resource->deleteReceivedBefore($cutoff);
    }
}
