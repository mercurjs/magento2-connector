<?php

declare(strict_types=1);

namespace Mercur\Connector\Model\Webhook;

interface HandlerInterface
{
    /**
     * @param array $data the event payload Mercur delivered
     */
    public function handle(string $event, array $data): void;
}
