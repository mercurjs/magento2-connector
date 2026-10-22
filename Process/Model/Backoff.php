<?php

declare(strict_types=1);

namespace Mercur\Process\Model;

class Backoff
{
    public const DEFAULT_BASE_SECONDS = 60;
    public const DEFAULT_CAP_SECONDS = 3600;

    public function delaySeconds(int $attempt, int $baseSeconds = self::DEFAULT_BASE_SECONDS, int $capSeconds = self::DEFAULT_CAP_SECONDS): int
    {
        $attempt = max(1, $attempt);
        $exponent = min($attempt - 1, 20);

        return (int) min($capSeconds, $baseSeconds * (2 ** $exponent));
    }

    public function nextRunAt(int $attempt, ?int $overrideSeconds = null, ?\DateTimeImmutable $now = null): string
    {
        $seconds = $overrideSeconds ?? $this->delaySeconds($attempt);
        $now = $now ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        return $now->modify('+' . max(0, $seconds) . ' seconds')->format('Y-m-d H:i:s');
    }
}
