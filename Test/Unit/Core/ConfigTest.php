<?php

declare(strict_types=1);

namespace Mercur\Test\Unit\Core;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\StoreManagerInterface;
use Mercur\Core\Model\Config;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    /**
     * Magento decrypts these paths on read because config.xml declares the Encrypted backend model,
     * so the values must come back exactly as the scope config returns them.
     */
    public function testEncryptedPathsAreReturnedAsReadFromScopeConfig(): void
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnMap([
            ['mercur/general/api_key', 'store', null, 'sk_secret'],
            ['mercur/general/publishable_key', 'store', null, 'pk_publishable'],
            ['mercur/webhook/secret', 'store', null, 'whsec'],
        ]);

        $config = new Config($scopeConfig, $this->createMock(StoreManagerInterface::class));

        self::assertSame('sk_secret', $config->getApiKey());
        self::assertSame('pk_publishable', $config->getPublishableKey());
        self::assertSame('whsec', $config->getWebhookSecret());
    }

    public function testMissingKeyIsEmpty(): void
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturn(null);

        $config = new Config($scopeConfig, $this->createMock(StoreManagerInterface::class));

        self::assertSame('', $config->getApiKey());
    }
}
