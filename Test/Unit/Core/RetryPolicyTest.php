<?php

declare(strict_types=1);

namespace Mercur\Test\Unit\Core;

use Mercur\Core\Model\Api\RetryPolicy;
use PHPUnit\Framework\TestCase;

final class RetryPolicyTest extends TestCase
{
    public function testRetriesTransientFailuresForReadsAndDeletes(): void
    {
        $policy = new RetryPolicy();

        foreach ([0, 429, 502, 503, 504] as $status) {
            self::assertTrue($policy->shouldRetry('GET', $status, 1, 2), "GET $status");
            self::assertTrue($policy->shouldRetry('DELETE', $status, 1, 2), "DELETE $status");
            self::assertFalse($policy->shouldRetry('POST', $status, 1, 2), "POST $status");
            self::assertTrue($policy->shouldRetry('POST', $status, 1, 2, true), "idempotent POST $status");
        }
    }

    public function testRetriesFiveHundredOnlyForSafeCalls(): void
    {
        $policy = new RetryPolicy();

        self::assertTrue($policy->shouldRetry('GET', 500, 1, 2));
        self::assertFalse($policy->shouldRetry('POST', 500, 1, 2));
        self::assertTrue($policy->shouldRetry('POST', 500, 1, 2, true));
        self::assertFalse($policy->shouldRetry('GET', 404, 1, 2));
        self::assertFalse($policy->shouldRetry('GET', 400, 1, 2));
    }

    public function testStopsAfterMaxRetries(): void
    {
        $policy = new RetryPolicy();

        self::assertTrue($policy->shouldRetry('GET', 503, 2, 2));
        self::assertFalse($policy->shouldRetry('GET', 503, 3, 2));
        self::assertFalse($policy->shouldRetry('GET', 503, 1, 0));
    }

    public function testDelayGrowsExponentiallyWithBoundedJitter(): void
    {
        $policy = new RetryPolicy();

        self::assertSame(500, $policy->delayMs(1, 500, 0.0));
        self::assertSame(1000, $policy->delayMs(2, 500, 0.0));
        self::assertSame(2000, $policy->delayMs(3, 500, 0.0));
        self::assertSame(625, $policy->delayMs(1, 500, 0.5));

        $random = $policy->delayMs(2, 500);
        self::assertGreaterThanOrEqual(1000, $random);
        self::assertLessThan(1500, $random);
        self::assertSame(0, $policy->delayMs(3, 0));
    }
}
