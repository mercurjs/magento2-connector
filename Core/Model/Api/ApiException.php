<?php

declare(strict_types=1);

namespace Mercur\Core\Model\Api;

class ApiException extends \RuntimeException
{
    public function __construct(
        string $message,
        private readonly int $status = 0,
        private readonly ?array $body = null
    ) {
        parent::__construct($message, $status);
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function getBody(): ?array
    {
        return $this->body;
    }
}
