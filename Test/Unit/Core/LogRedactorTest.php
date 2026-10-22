<?php

declare(strict_types=1);

namespace Mercur\Test\Unit\Core;

use Mercur\Core\Model\Api\LogRedactor;
use PHPUnit\Framework\TestCase;

final class LogRedactorTest extends TestCase
{
    public function testMasksCredentialHeadersRegardlessOfCase(): void
    {
        $headers = (new LogRedactor())->headers([
            'Authorization' => 'Basic abc',
            'X-Publishable-Api-Key' => 'pk_123',
            'x-mercur-signature' => 'sha256=ff',
            'Accept' => 'application/json',
        ]);

        self::assertSame(LogRedactor::MASK, $headers['Authorization']);
        self::assertSame(LogRedactor::MASK, $headers['X-Publishable-Api-Key']);
        self::assertSame(LogRedactor::MASK, $headers['x-mercur-signature']);
        self::assertSame('application/json', $headers['Accept']);
    }

    public function testMasksSecretKeysInsideJsonBodies(): void
    {
        $body = (new LogRedactor())->body('{"email":"a@b.c","password":"hunter2","nested":{"token":"t"}}');

        self::assertSame('{"email":"a@b.c","password":"***","nested":{"token":"***"}}', $body);
    }

    public function testTruncatesLongBodies(): void
    {
        $redactor = new LogRedactor(10);

        self::assertSame('0123456789… [5 more bytes]', $redactor->body('012345678912345'));
        self::assertSame('short', $redactor->body('short'));
        self::assertNull($redactor->body(null));
    }
}
