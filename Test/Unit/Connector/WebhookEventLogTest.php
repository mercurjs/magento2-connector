<?php

declare(strict_types=1);

namespace Mercur\Test\Unit\Connector;

use Mercur\Connector\Model\Webhook\EventLog;
use PHPUnit\Framework\TestCase;

final class WebhookEventLogTest extends TestCase
{
    public function testEventIdPrefersTheDeliveryHeaderThenThePayloadIdThenTheBodyDigest(): void
    {
        $body = '{"id":"evt_1","event":"offer.updated"}';

        self::assertSame('dlv_9', EventLog::eventId('dlv_9', ['id' => 'evt_1'], $body));
        self::assertSame('evt_1', EventLog::eventId(null, ['id' => 'evt_1'], $body));
        self::assertSame('evt_1', EventLog::eventId('  ', ['id' => 'evt_1'], $body));
        self::assertSame(sha1($body), EventLog::eventId(null, ['id' => ''], $body));
        self::assertSame(sha1($body), EventLog::eventId(null, [], $body));
    }

    public function testStalenessUsesCreatedAtOnlyWhenConfigured(): void
    {
        $now = 1_700_000_000;
        $old = ['created_at' => gmdate('c', $now - 600)];

        self::assertFalse(EventLog::isStale($old, 0, $now));
        self::assertTrue(EventLog::isStale($old, 300, $now));
        self::assertFalse(EventLog::isStale(['created_at' => gmdate('c', $now - 100)], 300, $now));
        self::assertFalse(EventLog::isStale([], 300, $now));
        self::assertFalse(EventLog::isStale(['created_at' => 'not a date'], 300, $now));
    }
}
