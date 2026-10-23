<?php

declare(strict_types=1);

namespace Mercur\Catalog\Model\Product\Image;

/**
 * Decides which Mercur images a Magento product should carry. Connector
 * images are named after a hash of their source URL, so a re-run recognises
 * them, drops the ones Mercur no longer has and leaves images added in
 * Magento alone.
 */
final class GalleryPlan
{
    public const PREFIX = 'mercur-';

    public const ROLES = ['image', 'small_image', 'thumbnail'];

    private const EXTENSIONS = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif'];

    /**
     * @return string[] source URLs in gallery order
     */
    public static function urlsFor(array $mercurProduct, ?array $variant, bool $isChild): array
    {
        if ($isChild && !empty($variant['thumbnail'])) {
            return [(string) $variant['thumbnail']];
        }

        $images = $mercurProduct['images'] ?? [];
        usort($images, static fn (array $a, array $b) => ((int) ($a['rank'] ?? 0)) <=> ((int) ($b['rank'] ?? 0)));

        $urls = [];
        foreach ($images as $image) {
            $url = (string) ($image['url'] ?? '');
            if ($url !== '' && !in_array($url, $urls, true)) {
                $urls[] = $url;
            }
        }
        if ($urls === [] && !empty($mercurProduct['thumbnail'])) {
            $urls[] = (string) $mercurProduct['thumbnail'];
        }

        return $urls;
    }

    public static function hash(string $url): string
    {
        return substr(sha1($url), 0, 12);
    }

    public static function extension(string $mime): ?string
    {
        return self::EXTENSIONS[strtolower($mime)] ?? null;
    }

    public static function fileName(string $url, string $mime): ?string
    {
        $extension = self::extension($mime);

        return $extension === null ? null : self::PREFIX . self::hash($url) . '.' . $extension;
    }

    /**
     * The URL hash of a gallery file the connector created, or null for any other file.
     */
    public static function ownedHash(string $file): ?string
    {
        return preg_match('#(?:^|/)' . self::PREFIX . '([0-9a-f]{12})(?:_\d+)?\.[a-z0-9]+$#i', $file, $match)
            ? strtolower($match[1])
            : null;
    }

    /**
     * @param array<int, array{id: int, file: string, types: string[]}> $existing entries already on the product
     * @param string[] $urls wanted images, in order
     * @return array{changed: bool, keep: int[], add: array<int, array{url: string, position: int, roles: string[]}>}
     */
    public static function plan(array $existing, array $urls): array
    {
        $wanted = [];
        foreach ($urls as $url) {
            $wanted[self::hash($url)] = $url;
        }

        $keep = [];
        $present = [];
        $rolesTaken = false;
        foreach ($existing as $entry) {
            $hash = self::ownedHash((string) $entry['file']);
            if ($hash !== null && !isset($wanted[$hash])) {
                continue;
            }
            $keep[] = (int) $entry['id'];
            if ($hash !== null) {
                $present[$hash] = true;
            }
            if (array_intersect(self::ROLES, $entry['types'] ?? []) !== []) {
                $rolesTaken = true;
            }
        }

        $add = [];
        $position = 0;
        foreach ($wanted as $hash => $url) {
            $position++;
            if (isset($present[$hash])) {
                continue;
            }
            $add[] = ['url' => $url, 'position' => $position, 'roles' => $position === 1 && !$rolesTaken ? self::ROLES : []];
        }

        return [
            'changed' => $add !== [] || count($keep) !== count($existing),
            'keep' => $keep,
            'add' => $add,
        ];
    }
}
