<?php

declare(strict_types=1);

namespace Mercur\Core\Model\Api;

/**
 * Decides whether a failed call is worth repeating. Status 0 is a transport
 * failure (DNS, connect, timeout) before any response arrived.
 */
class RetryPolicy
{
    /** @var int[] */
    private const TRANSIENT_STATUSES = [0, 429, 502, 503, 504];

    public function shouldRetry(string $method, int $status, int $attempt, int $maxRetries, bool $idempotent = false): bool
    {
        if ($attempt > $maxRetries) {
            return false;
        }
        $method = strtoupper($method);
        $safe = in_array($method, ['GET', 'HEAD', 'DELETE'], true) || $idempotent;

        if (in_array($status, self::TRANSIENT_STATUSES, true)) {
            return $safe;
        }

        return $status === 500 && $safe;
    }

    /**
     * Exponential delay with up to 50% jitter so parallel runners do not hammer
     * the API in lock-step. $jitter in [0,1) can be fixed by callers that need
     * deterministic timing.
     */
    public function delayMs(int $attempt, int $baseMs, ?float $jitter = null): int
    {
        $attempt = max(1, $attempt);
        $baseMs = max(0, $baseMs);
        $exponential = $baseMs * (2 ** min($attempt - 1, 10));
        $jitter = $jitter ?? (random_int(0, 1000) / 1000);

        return (int) round($exponential * (1 + 0.5 * max(0.0, min(0.999, $jitter))));
    }
}
