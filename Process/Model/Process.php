<?php

declare(strict_types=1);

namespace Mercur\Process\Model;

use Magento\Framework\Model\AbstractModel;
use Mercur\Process\Model\ResourceModel\Process as ProcessResource;

class Process extends AbstractModel
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PENDING_RETRY = 'pending_retry';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_ERROR = 'error';
    public const STATUS_TIMEOUT = 'timeout';
    public const STATUS_STOPPED = 'stopped';

    public const FLUSH_EVERY_LINES = 20;
    public const FLUSH_EVERY_SECONDS = 5;

    /** @var array<int, string> */
    public const RUNNABLE_STATUSES = [self::STATUS_PENDING, self::STATUS_PENDING_RETRY];

    /** @var array<int, string> */
    public const ACTIVE_STATUSES = [self::STATUS_PENDING, self::STATUS_PENDING_RETRY, self::STATUS_PROCESSING];

    /** @var array<int, string> */
    public const FINAL_STATUSES = [self::STATUS_COMPLETED, self::STATUS_ERROR, self::STATUS_TIMEOUT, self::STATUS_STOPPED];

    /** @var null|\Closure(self, string): void */
    private ?\Closure $outputFlusher = null;

    private string $unflushed = '';

    private int $unflushedLines = 0;

    private float $lastFlushAt = 0.0;

    protected function _construct(): void
    {
        $this->_init(ProcessResource::class);
    }

    public function getCode(): string
    {
        return (string) $this->getData('code');
    }

    public function getStatus(): string
    {
        return (string) $this->getData('status');
    }

    public function getParentId(): ?int
    {
        $value = $this->getData('parent_id');

        return $value === null || $value === '' ? null : (int) $value;
    }

    public function getAttempts(): int
    {
        return (int) $this->getData('attempts');
    }

    public function getMaxAttempts(): int
    {
        return max(1, (int) $this->getData('max_attempts'));
    }

    public function getHash(): string
    {
        return (string) $this->getData('hash');
    }

    public function getParams(): array
    {
        $raw = $this->getData('params');
        if (!$raw) {
            return [];
        }
        $decoded = json_decode((string) $raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    public function setParams(array $params): self
    {
        return $this->setData('params', json_encode($params));
    }

    public function isPending(): bool
    {
        return $this->getStatus() === self::STATUS_PENDING;
    }

    public function isRunnable(): bool
    {
        return in_array($this->getStatus(), self::RUNNABLE_STATUSES, true);
    }

    public function isActive(): bool
    {
        return in_array($this->getStatus(), self::ACTIVE_STATUSES, true);
    }

    public function isFinished(): bool
    {
        return in_array($this->getStatus(), self::FINAL_STATUSES, true);
    }

    public function canRetry(): bool
    {
        return $this->getAttempts() < $this->getMaxAttempts();
    }

    /**
     * @param null|\Closure(self, string): void $flusher receives the lines buffered since the last flush
     */
    public function setOutputFlusher(?\Closure $flusher): self
    {
        $this->outputFlusher = $flusher;
        $this->unflushed = '';
        $this->unflushedLines = 0;
        $this->lastFlushAt = microtime(true);

        return $this;
    }

    public function output(string $line): self
    {
        $stamp = (new \DateTimeImmutable())->format('H:i:s');
        $entry = "[$stamp] $line\n";
        $this->setData('output', ($this->getData('output') ?? '') . $entry);

        if ($this->outputFlusher !== null) {
            $this->unflushed .= $entry;
            $this->unflushedLines++;
            if ($this->unflushedLines >= self::FLUSH_EVERY_LINES || microtime(true) - $this->lastFlushAt >= self::FLUSH_EVERY_SECONDS) {
                $this->flushOutput();
            }
        }

        return $this;
    }

    public function flushOutput(): void
    {
        if ($this->outputFlusher === null || $this->unflushed === '') {
            return;
        }
        $chunk = $this->unflushed;
        $this->unflushed = '';
        $this->unflushedLines = 0;
        $this->lastFlushAt = microtime(true);
        ($this->outputFlusher)($this, $chunk);
    }
}
