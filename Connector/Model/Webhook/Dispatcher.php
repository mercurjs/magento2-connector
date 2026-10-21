<?php

declare(strict_types=1);

namespace Mercur\Connector\Model\Webhook;

use Psr\Log\LoggerInterface;

class Dispatcher
{
    /**
     * @param array<string, HandlerInterface[]> $handlers event name => handlers; `*` matches every event
     */
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly array $handlers = []
    ) {
    }

    public function dispatch(string $event, array $data): void
    {
        $handlers = array_merge($this->handlers[$event] ?? [], $this->handlers['*'] ?? []);
        if ($handlers === []) {
            $this->logger->info('Mercur webhook with no handler', ['event' => $event]);

            return;
        }
        foreach ($handlers as $handler) {
            $handler->handle($event, $data);
        }
    }
}
