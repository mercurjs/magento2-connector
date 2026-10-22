<?php

declare(strict_types=1);

namespace Mercur\Process\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;

class ProcessConfig
{
    private const PATH_TIMEOUT_SEC = 'mercur/process/timeout_sec';
    private const PATH_HISTORY_DAYS = 'mercur/process/history_days';
    private const PATH_MAX_ATTEMPTS = 'mercur/process/max_attempts';

    public const DEFAULT_TIMEOUT_SEC = 3600;
    public const DEFAULT_HISTORY_DAYS = 30;
    public const DEFAULT_MAX_ATTEMPTS = 3;

    public function __construct(private readonly ScopeConfigInterface $scopeConfig)
    {
    }

    public function getTimeoutSec(): int
    {
        return max(1, (int) $this->scopeConfig->getValue(self::PATH_TIMEOUT_SEC) ?: self::DEFAULT_TIMEOUT_SEC);
    }

    public function getHistoryDays(): int
    {
        return max(1, (int) $this->scopeConfig->getValue(self::PATH_HISTORY_DAYS) ?: self::DEFAULT_HISTORY_DAYS);
    }

    public function getMaxAttempts(): int
    {
        return max(1, (int) $this->scopeConfig->getValue(self::PATH_MAX_ATTEMPTS) ?: self::DEFAULT_MAX_ATTEMPTS);
    }
}
