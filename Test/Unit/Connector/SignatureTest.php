<?php

declare(strict_types=1);

namespace Mercur\Test\Unit\Connector;

use Mercur\Connector\Model\Webhook\Signature;
use PHPUnit\Framework\TestCase;

final class SignatureTest extends TestCase
{
    public function testSignsLikeMercur(): void
    {
        $signature = new Signature();
        $body = '{"event":"offer.created","data":{"id":"offer_1"}}';

        self::assertSame('sha256=' . hash_hmac('sha256', $body, 'secret'), $signature->sign($body, 'secret'));
    }

    public function testVerifyAcceptsMatchingHeader(): void
    {
        $signature = new Signature();

        self::assertTrue($signature->verify('body', 'secret', $signature->sign('body', 'secret')));
    }

    public function testVerifyRejectsMissingSecretOrHeader(): void
    {
        $signature = new Signature();

        self::assertFalse($signature->verify('body', '', 'sha256=abc'));
        self::assertFalse($signature->verify('body', 'secret', null));
        self::assertFalse($signature->verify('body', 'secret', 'sha256=wrong'));
    }
}
