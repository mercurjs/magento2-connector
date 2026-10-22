<?php

declare(strict_types=1);

namespace Mercur\Process\Api;

/**
 * Thrown by a task that could not finish now but expects to succeed later
 * (rate limit, upstream outage). The runner reschedules the process instead
 * of marking it failed, up to its max attempts.
 */
class RetryLaterException extends \RuntimeException
{
    public function __construct(
        string $message = 'Retry later',
        private readonly ?int $delaySeconds = null,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function getDelaySeconds(): ?int
    {
        return $this->delaySeconds;
    }
}
