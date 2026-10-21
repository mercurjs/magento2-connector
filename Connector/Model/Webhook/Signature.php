<?php

declare(strict_types=1);

namespace Mercur\Connector\Model\Webhook;

class Signature
{
    public function sign(string $body, string $secret): string
    {
        return 'sha256=' . hash_hmac('sha256', $body, $secret);
    }

    public function verify(string $body, string $secret, ?string $header): bool
    {
        if ($secret === '' || $header === null || $header === '') {
            return false;
        }

        return hash_equals($this->sign($body, $secret), $header);
    }
}
