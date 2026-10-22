<?php

declare(strict_types=1);

namespace Mercur\Core\Model\Api;

class LogRedactor
{
    public const MASK = '***';

    /** @var string[] lower-case header names that never reach the log */
    private const SECRET_HEADERS = ['authorization', 'x-publishable-api-key', 'x-mercur-signature', 'cookie', 'set-cookie'];

    /** @var string[] lower-case body keys whose values are masked */
    private const SECRET_KEYS = ['api_key', 'secret', 'password', 'token', 'authorization', 'publishable_key'];

    public function __construct(private readonly int $bodyLimit = 2000)
    {
    }

    /**
     * @param array<string, string> $headers
     * @return array<string, string>
     */
    public function headers(array $headers): array
    {
        $result = [];
        foreach ($headers as $name => $value) {
            $result[$name] = in_array(strtolower((string) $name), self::SECRET_HEADERS, true) ? self::MASK : (string) $value;
        }

        return $result;
    }

    public function body(?string $body): ?string
    {
        if ($body === null || $body === '') {
            return $body;
        }
        $decoded = json_decode($body, true);
        if (is_array($decoded)) {
            $body = (string) json_encode($this->maskKeys($decoded), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        return $this->truncate($body);
    }

    public function truncate(string $text): string
    {
        if (strlen($text) <= $this->bodyLimit) {
            return $text;
        }

        return substr($text, 0, $this->bodyLimit) . sprintf('… [%d more bytes]', strlen($text) - $this->bodyLimit);
    }

    private function maskKeys(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), self::SECRET_KEYS, true)) {
                $data[$key] = self::MASK;
            } elseif (is_array($value)) {
                $data[$key] = $this->maskKeys($value);
            }
        }

        return $data;
    }
}
