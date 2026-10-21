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
        private readonly Config $config,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(): ResultInterface
    {
        $result = $this->jsonFactory->create();
        $body = (string) $this->request->getContent();
        $header = $this->request->getHeader('x-mercur-signature') ?: null;

        if (!$this->signature->verify($body, $this->config->getWebhookSecret(), is_string($header) ? $header : null)) {
            return $result->setHttpResponseCode(401)->setData(['error' => 'invalid signature']);
        }

        try {
            $payload = $this->json->unserialize($body);
        } catch (\InvalidArgumentException) {
            return $result->setHttpResponseCode(400)->setData(['error' => 'invalid json']);
        }

        $event = (string) ($payload['event'] ?? $this->request->getHeader('x-mercur-event') ?? '');
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];

        try {
            $this->dispatcher->dispatch($event, $data);
        } catch (\Throwable $e) {
            $this->logger->error('Mercur webhook handling failed', ['event' => $event, 'exception' => $e]);

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
}
