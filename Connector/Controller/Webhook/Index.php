<?php

declare(strict_types=1);

namespace Mercur\Connector\Controller\Webhook;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Mercur\Connector\Model\Webhook\Dispatcher;
use Mercur\Connector\Model\Webhook\EventLog;
use Mercur\Connector\Model\Webhook\Signature;
use Mercur\Core\Model\Config;
use Psr\Log\LoggerInterface;

/**
 * POST /mercur/webhook — the endpoint registered in Mercur under Settings > Magento.
 */
class Index implements HttpPostActionInterface, CsrfAwareActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly JsonFactory $jsonFactory,
        private readonly Json $json,
        private readonly Signature $signature,
        private readonly Dispatcher $dispatcher,
        private readonly EventLog $eventLog,
        private readonly Config $config,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(): ResultInterface
    {
        $result = $this->jsonFactory->create();
        $body = (string) $this->request->getContent();

        if (!$this->signature->verify($body, $this->config->getWebhookSecret(), $this->header('x-mercur-signature'))) {
            return $result->setHttpResponseCode(401)->setData(['error' => 'invalid signature']);
        }

        try {
            $payload = $this->json->unserialize($body);
        } catch (\InvalidArgumentException) {
            return $result->setHttpResponseCode(400)->setData(['error' => 'invalid json']);
        }
        if (!is_array($payload)) {
            return $result->setHttpResponseCode(400)->setData(['error' => 'invalid json']);
        }

        $event = (string) ($payload['event'] ?? $this->header('x-mercur-event') ?? '');
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $eventId = EventLog::eventId($this->header('x-mercur-delivery'), $payload, $body);

        if (EventLog::isStale($payload, $this->config->getWebhookMaxAgeSec())) {
            $this->logger->info('Mercur webhook ignored as stale', ['event' => $event, 'id' => $eventId]);

            return $result->setData(['received' => true, 'event' => $event, 'stale' => true]);
        }

        if (!$this->eventLog->markReceived($eventId, $event)) {
            return $result->setData(['received' => true, 'event' => $event, 'duplicate' => true]);
        }

        try {
            $this->dispatcher->dispatch($event, $data);
        } catch (\Throwable $e) {
            $this->eventLog->forget($eventId);
            $this->logger->error('Mercur webhook handling failed', ['event' => $event, 'id' => $eventId, 'exception' => $e]);

            return $result->setHttpResponseCode(500)->setData(['error' => 'handler failed']);
        }

        return $result->setData(['received' => true, 'event' => $event]);
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    private function header(string $name): ?string
    {
        $value = $this->request->getHeader($name);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
