<?php

declare(strict_types=1);

namespace Mercur\Catalog\Model\Product\Image;

use Magento\Framework\HTTP\Client\CurlFactory;
use Mercur\Catalog\Model\CatalogConfig;

/**
 * Fetches product images within the configured size and time limits. Results,
 * failures included, are kept for the run so children inheriting the product
 * images do not download them again.
 */
class ImageDownloader
{
    /** @var array<string, array{0: array{bytes: string, mime: string}|null, 1: string|null}> */
    private array $cache = [];

    public function __construct(
        private readonly CurlFactory $curlFactory,
        private readonly CatalogConfig $config
    ) {
    }

    /**
     * @return array{bytes: string, mime: string}|null null with $reason set when the image is unusable
     */
    public function fetch(string $url, ?string &$reason = null): ?array
    {
        if (!array_key_exists($url, $this->cache)) {
            $this->cache[$url] = $this->download($url);
        }
        [$image, $reason] = $this->cache[$url];

        return $image;
    }

    /**
     * @return array{0: array{bytes: string, mime: string}|null, 1: string|null}
     */
    private function download(string $url): array
    {
        $maxBytes = $this->config->getImageMaxBytes();
        try {
            $curl = $this->curlFactory->create();
            $curl->setTimeout($this->config->getImageTimeoutSec());
            $curl->setOption(CURLOPT_FOLLOWLOCATION, true);
            $curl->setOption(CURLOPT_MAXREDIRS, 3);
            $curl->setOption(CURLOPT_MAXFILESIZE, $maxBytes);
            $curl->get($url);
            $status = (int) $curl->getStatus();
            $body = (string) $curl->getBody();
        } catch (\Throwable $e) {
            return [null, 'request failed: ' . $e->getMessage()];
        }

        if ($status < 200 || $status >= 300) {
            return [null, "HTTP $status"];
        }
        if (strlen($body) > $maxBytes) {
            return [null, sprintf('larger than %d MB', intdiv($maxBytes, 1024 * 1024))];
        }
        $info = @getimagesizefromstring($body);
        $mime = (string) ($info['mime'] ?? '');
        if ($mime === '') {
            return [null, 'not an image'];
        }
        if (GalleryPlan::extension($mime) === null) {
            return [null, "unsupported image type $mime"];
        }

        return [['bytes' => $body, 'mime' => $mime], null];
    }
}
