<?php

declare(strict_types=1);

namespace Mercur\Catalog\Model\Product\Image;

use Magento\Catalog\Api\Data\ProductAttributeMediaGalleryEntryInterfaceFactory;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\Api\Data\ImageContentInterfaceFactory;
use Mercur\Catalog\Model\Product\ImportResult;

/**
 * Puts the wanted Mercur images on a product as media gallery entries. New
 * entries carry their image content, which the product repository stores
 * on save; entries left out of the list are removed by it.
 */
class GalleryWriter
{
    public function __construct(
        private readonly ImageDownloader $downloader,
        private readonly ProductAttributeMediaGalleryEntryInterfaceFactory $entryFactory,
        private readonly ImageContentInterfaceFactory $contentFactory
    ) {
    }

    /**
     * @param string[] $urls
     */
    public function apply(ProductInterface $product, array $urls, string $label, ImportResult $result): void
    {
        $current = $product->getMediaGalleryEntries() ?? [];
        $existing = [];
        foreach ($current as $entry) {
            $existing[] = ['id' => (int) $entry->getId(), 'file' => (string) $entry->getFile(), 'types' => $entry->getTypes() ?? []];
        }
        $plan = GalleryPlan::plan($existing, $urls);
        if (!$plan['changed']) {
            return;
        }

        $entries = [];
        foreach ($current as $entry) {
            if (in_array((int) $entry->getId(), $plan['keep'], true)) {
                $entries[] = $entry;
            }
        }

        $added = 0;
        foreach ($plan['add'] as $add) {
            $reason = null;
            $image = $this->downloader->fetch($add['url'], $reason);
            if ($image === null) {
                $result->warn(sprintf('image %s skipped for %s: %s', $add['url'], $product->getSku(), $reason));
                $result->countImage(false);
                continue;
            }
            $content = $this->contentFactory->create();
            $content->setName((string) GalleryPlan::fileName($add['url'], $image['mime']));
            $content->setType($image['mime']);
            $content->setBase64EncodedData(base64_encode($image['bytes']));

            $entry = $this->entryFactory->create();
            $entry->setMediaType('image');
            $entry->setLabel($label);
            $entry->setPosition($add['position']);
            $entry->setDisabled(false);
            $entry->setTypes($add['roles']);
            $entry->setContent($content);
            $entries[] = $entry;
            $added++;
            $result->countImage(true);
        }

        // When every new image failed to download the gallery is left as it is, so an outage never strips it.
        if ($added === 0 && $plan['add'] !== []) {
            return;
        }
        $product->setMediaGalleryEntries($entries);
    }
}
