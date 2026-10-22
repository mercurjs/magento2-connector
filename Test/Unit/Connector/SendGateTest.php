<?php

declare(strict_types=1);

namespace Mercur\Test\Unit\Connector;

use Mercur\Connector\Model\Order\SendGate;
use PHPUnit\Framework\TestCase;

final class SendGateTest extends TestCase
{
    public function testSendsOnlyOnceInAnAllowedStatusWithACart(): void
    {
        $allowed = ['pending', 'processing'];

        self::assertTrue(SendGate::shouldSend('pending', $allowed, false, true));
        self::assertTrue(SendGate::shouldSend('processing', $allowed, false, true));
        self::assertFalse(SendGate::shouldSend('holded', $allowed, false, true));
        self::assertFalse(SendGate::shouldSend('pending', $allowed, true, true));
        self::assertFalse(SendGate::shouldSend('pending', $allowed, false, false));
        self::assertFalse(SendGate::shouldSend('pending', [], false, true));
    }
}
