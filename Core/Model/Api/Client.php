<?php

declare(strict_types=1);

namespace Mercur\Core\Model\Api;

use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\HTTP\Client\CurlFactory;
use Magento\Framework\Serialize\Serializer\Json;
use Mercur\Core\Model\Config;
use Psr\Log\LoggerInterface;

class Client
{
    public function __construct(
        private readonly CurlFactory $curlFactory,
        private readonly Json $json,
        private readonly Config $config,
        private readonly LoggerInterface $logger
    ) {
    }

    public function get(string $path, array $query = [], array $headers = [], ?int $storeId = null): array
    {
        $url = $this->url($path, $query, $storeId);
        $curl = $this->prepare($headers, $storeId);
        $curl->get($url);

        return $this->handle($curl, 'GET', $url);
    }

    public function post(string $path, array $body = [], array $headers = [], ?int $storeId = null): array
    {
        $url = $this->url($path, [], $storeId);
        $curl = $this->prepare($headers + ['Content-Type' => 'application/json'], $storeId);
        $curl->post($url, $this->json->serialize($body));

        return $this->handle($curl, 'POST', $url);
    }

    public function delete(string $path, array $headers = [], ?int $storeId = null): array
    {
        $url = $this->url($path, [], $storeId);
        $curl = $this->prepare($headers, $storeId);
        $curl->setOption(CURLOPT_CUSTOMREQUEST, 'DELETE');
        $curl->get($url);

        return $this->handle($curl, 'DELETE', $url);
    }

    public function adminHeaders(?int $storeId = null): array
    {
        return ['Authorization' => 'Basic ' . base64_encode($this->config->getApiKey($storeId) . ':')];
    }

    public function storeHeaders(?int $storeId = null): array
    {
        return ['x-publishable-api-key' => $this->config->getPublishableKey($storeId)];
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
        $curl->setTimeout($this->config->getTimeout());
        $curl->addHeader('Accept', 'application/json');
        $curl->addHeader('User-Agent', 'mercur-magento2-connector');
        foreach ($headers as $name => $value) {
            $curl->addHeader($name, $value);
        }

        return $curl;
    }

    private function handle(Curl $curl, string $method, string $url): array
    {
        $status = $curl->getStatus();
        $raw = $curl->getBody();
        $body = null;

        if ($raw !== '') {
            try {
                $decoded = $this->json->unserialize($raw);
                $body = is_array($decoded) ? $decoded : null;
            } catch (\InvalidArgumentException) {
                $body = null;
            }
        }

        if ($status < 200 || $status >= 300) {
            $message = $body['message'] ?? sprintf('%s %s failed with status %d', $method, $url, $status);
            $this->logger->warning('Mercur API error', ['method' => $method, 'url' => $url, 'status' => $status, 'body' => $raw]);

            throw new ApiException((string) $message, $status, $body);
        }

        return $body ?? [];
    }
}
