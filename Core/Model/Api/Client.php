<?php

declare(strict_types=1);

namespace Mercur\Core\Model\Api;

use Magento\Framework\DataObject;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\HTTP\Client\CurlFactory;
use Magento\Framework\Serialize\Serializer\Json;
use Mercur\Core\Model\Config;
use Mercur\Core\Model\Config\Source\LogLevel;
use Psr\Log\LoggerInterface;

class Client
{
    public function __construct(
        private readonly CurlFactory $curlFactory,
        private readonly Json $json,
        private readonly Config $config,
        private readonly LoggerInterface $logger,
        private readonly EventManager $eventManager,
        private readonly RetryPolicy $retryPolicy,
        private readonly LogRedactor $redactor
    ) {
    }

    public function get(string $path, array $query = [], array $headers = [], ?int $storeId = null): array
    {
        return $this->send('GET', $path, $query, null, $headers, $storeId);
    }

    /**
     * @param array{idempotent?: bool} $options `idempotent` lets a POST be retried on transient failures
     */
    public function post(string $path, array $body = [], array $headers = [], ?int $storeId = null, array $options = []): array
    {
        return $this->send('POST', $path, [], $body, $headers + ['Content-Type' => 'application/json'], $storeId, $options);
    }

    public function delete(string $path, array $headers = [], ?int $storeId = null): array
    {
        return $this->send('DELETE', $path, [], null, $headers, $storeId);
    }

    public function adminHeaders(?int $storeId = null): array
    {
        return ['Authorization' => 'Basic ' . base64_encode($this->config->getApiKey($storeId) . ':')];
    }

    public function storeHeaders(?int $storeId = null): array
    {
        return ['x-publishable-api-key' => $this->config->getPublishableKey($storeId)];
    }

    /**
     * @param array{idempotent?: bool} $options
     */
    public function send(string $method, string $path, array $query = [], ?array $body = null, array $headers = [], ?int $storeId = null, array $options = []): array
    {
        $url = $this->url($path, $query, $storeId);
        $payload = $body === null ? null : $this->json->serialize($body);
        $idempotent = (bool) ($options['idempotent'] ?? false);
        $maxRetries = $this->config->getRetries($storeId);
        $baseDelay = $this->config->getRetryDelayMs($storeId);
        $logLevel = $this->config->getLogLevel($storeId);

        $request = new DataObject([
            'method' => $method,
            'url' => $url,
            'path' => $path,
            'headers' => $headers,
            'body' => $body,
            'store_id' => $storeId,
        ]);
        $this->eventManager->dispatch('mercur_api_call_before', ['request' => $request]);
        $headers = is_array($request->getData('headers')) ? $request->getData('headers') : $headers;

        $attempt = 0;
        while (true) {
            $attempt++;
            $started = microtime(true);
            $exception = null;
            $status = 0;
            $raw = '';

            try {
                $curl = $this->prepare($headers, $storeId);
                $this->transmit($curl, $method, $url, $payload);
                $status = (int) $curl->getStatus();
                $raw = (string) $curl->getBody();
            } catch (\Throwable $e) {
                $exception = $e;
            }
            $durationMs = (int) round((microtime(true) - $started) * 1000);

            $decoded = $this->decode($raw);
            $ok = $exception === null && $status >= 200 && $status < 300;
            $this->log($logLevel, $ok, $method, $url, $headers, $payload, $status, $raw, $durationMs, $attempt, $exception);
            $this->eventManager->dispatch('mercur_api_call_after', [
                'request' => $request,
                'status' => $status,
                'body' => $decoded,
                'duration_ms' => $durationMs,
                'attempt' => $attempt,
                'exception' => $exception,
            ]);

            if ($ok) {
                return $decoded ?? [];
            }

            if ($this->retryPolicy->shouldRetry($method, $status, $attempt, $maxRetries, $idempotent)) {
                usleep($this->retryPolicy->delayMs($attempt, $baseDelay) * 1000);
                continue;
            }

            if ($exception !== null) {
                throw new ApiException(sprintf('%s %s failed: %s', $method, $url, $exception->getMessage()), 0, null);
            }
            $message = $decoded['message'] ?? sprintf('%s %s failed with status %d', $method, $url, $status);

            throw new ApiException((string) $message, $status, $decoded);
        }
    }

    private function transmit(Curl $curl, string $method, string $url, ?string $payload): void
    {
        switch ($method) {
            case 'POST':
                $curl->post($url, $payload ?? '');
                break;
            case 'GET':
                $curl->get($url);
                break;
            default:
                $curl->setOption(CURLOPT_CUSTOMREQUEST, $method);
                if ($payload !== null) {
                    $curl->setOption(CURLOPT_POSTFIELDS, $payload);
                }
                $curl->get($url);
        }
    }

    private function decode(string $raw): ?array
    {
        if ($raw === '') {
            return null;
        }
        try {
            $decoded = $this->json->unserialize($raw);
        } catch (\InvalidArgumentException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    private function log(string $level, bool $ok, string $method, string $url, array $headers, ?string $payload, int $status, string $raw, int $durationMs, int $attempt, ?\Throwable $exception): void
    {
        if ($level === LogLevel::OFF || ($ok && $level === LogLevel::ERRORS)) {
            return;
        }
        $context = ['method' => $method, 'url' => $url, 'status' => $status, 'duration_ms' => $durationMs, 'attempt' => $attempt];
        if ($level === LogLevel::REQUESTS_AND_BODIES || !$ok) {
            $context['headers'] = $this->redactor->headers($headers);
            $context['request_body'] = $this->redactor->body($payload);
            $context['response_body'] = $this->redactor->body($raw);
        }
        if ($exception !== null) {
            $context['error'] = $exception->getMessage();
        }

        if ($ok) {
            $this->logger->info('Mercur API call', $context);
        } else {
            $this->logger->warning('Mercur API error', $context);
        }
    }

    private function url(string $path, array $query, ?int $storeId): string
    {
        $url = $this->config->getApiUrl($storeId) . '/' . ltrim($path, '/');
        $flat = $this->flattenQuery($query);

        return $flat === [] ? $url : $url . '?' . http_build_query($flat);
    }

    /**
     * Medusa reads operator maps as `updated_at[$gte]=...`, which http_build_query
     * produces from nested arrays, but it URL-encodes `$`; Medusa accepts both.
     */
    private function flattenQuery(array $query): array
    {
        return array_filter($query, static fn ($value) => $value !== null && $value !== '');
    }

    private function prepare(array $headers, ?int $storeId): Curl
    {
        $curl = $this->curlFactory->create();
        $curl->setTimeout($this->config->getTimeout($storeId));
        $curl->addHeader('Accept', 'application/json');
        $curl->addHeader('User-Agent', 'mercur-magento2-connector');
        foreach ($headers as $name => $value) {
            $curl->addHeader($name, $value);
        }

        return $curl;
    }
}
