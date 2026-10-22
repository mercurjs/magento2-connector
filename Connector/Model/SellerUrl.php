<?php

declare(strict_types=1);

namespace Mercur\Connector\Model;

use Magento\Framework\UrlInterface;

/**
 * Storefront seller pages live at /seller/{handle} (routed by Mercur_Frontend).
 */
class SellerUrl
{
    public const PATH_PREFIX = 'seller';

    public function __construct(private readonly UrlInterface $url)
    {
    }

    public function get(string $handle): string
    {
        return $handle === '' ? '' : $this->url->getUrl('', ['_direct' => self::PATH_PREFIX . '/' . rawurlencode($handle)]);
    }
}
