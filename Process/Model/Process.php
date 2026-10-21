<?php

declare(strict_types=1);

namespace Mercur\Process\Model;

use Magento\Framework\Model\AbstractModel;
use Mercur\Process\Model\ResourceModel\Process as ProcessResource;

class Process extends AbstractModel
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_ERROR = 'error';
    public const STATUS_CANCELLED = 'cancelled';

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

    public function output(string $line): self
    {
        $stamp = (new \DateTimeImmutable())->format('H:i:s');
        $this->setData('output', ($this->getData('output') ?? '') . "[$stamp] $line\n");

        return $this;
    }
}
