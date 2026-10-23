<?php

declare(strict_types=1);

namespace Mercur\Test\Unit\Catalog;

use Mercur\Catalog\Model\Product\Image\GalleryPlan;
use PHPUnit\Framework\TestCase;

final class GalleryPlanTest extends TestCase
{
    private const A = 'https://cdn.example/a.png';
    private const B = 'https://cdn.example/b.png';

    public function testProductImagesAreOrderedByRankWithTheThumbnailAsFallback(): void
    {
        $product = ['thumbnail' => self::A, 'images' => [['url' => self::B, 'rank' => 1], ['url' => self::A, 'rank' => 0], ['url' => self::A, 'rank' => 2]]];

        self::assertSame([self::A, self::B], GalleryPlan::urlsFor($product, null, false));
        self::assertSame([self::A], GalleryPlan::urlsFor(['thumbnail' => self::A], null, false));
        self::assertSame([], GalleryPlan::urlsFor([], null, false));
    }

    public function testAChildUsesItsVariantThumbnailOrInheritsTheProductImages(): void
    {
        $product = ['images' => [['url' => self::A, 'rank' => 0]]];

        self::assertSame([self::B], GalleryPlan::urlsFor($product, ['thumbnail' => self::B], true));
        self::assertSame([self::A], GalleryPlan::urlsFor($product, ['thumbnail' => null], true));
    }

    public function testFileNamesAreDerivedFromTheUrlAndRecognisedAgain(): void
    {
        $file = GalleryPlan::fileName(self::A, 'image/png');

        self::assertSame('mercur-' . GalleryPlan::hash(self::A) . '.png', $file);
        self::assertSame(GalleryPlan::hash(self::A), GalleryPlan::ownedHash('/m/e/' . $file));
        self::assertSame(GalleryPlan::hash(self::A), GalleryPlan::ownedHash('/m/e/mercur-' . GalleryPlan::hash(self::A) . '_1.png'));
        self::assertNull(GalleryPlan::ownedHash('/p/h/photo.jpg'));
        self::assertNull(GalleryPlan::fileName(self::A, 'image/webp'));
        self::assertSame('jpg', GalleryPlan::extension('image/jpeg'));
    }

    public function testAnEmptyGalleryGetsEveryImageWithRolesOnTheFirst(): void
    {
        $plan = GalleryPlan::plan([], [self::A, self::B]);

        self::assertTrue($plan['changed']);
        self::assertSame([], $plan['keep']);
        self::assertSame([
            ['url' => self::A, 'position' => 1, 'roles' => GalleryPlan::ROLES],
            ['url' => self::B, 'position' => 2, 'roles' => []],
        ], $plan['add']);
    }

    public function testTheSameImagesAgainChangeNothing(): void
    {
        $existing = [
            ['id' => 1, 'file' => '/m/e/mercur-' . GalleryPlan::hash(self::A) . '.png', 'types' => GalleryPlan::ROLES],
            ['id' => 2, 'file' => '/m/e/mercur-' . GalleryPlan::hash(self::B) . '.png', 'types' => []],
        ];

        $plan = GalleryPlan::plan($existing, [self::A, self::B]);

        self::assertFalse($plan['changed']);
        self::assertSame([1, 2], $plan['keep']);
    }

    public function testAWithdrawnImageIsDroppedAndAdminImagesStay(): void
    {
        $existing = [
            ['id' => 1, 'file' => '/m/e/mercur-' . GalleryPlan::hash(self::A) . '.png', 'types' => GalleryPlan::ROLES],
            ['id' => 2, 'file' => '/m/e/mercur-' . GalleryPlan::hash(self::B) . '.png', 'types' => []],
            ['id' => 3, 'file' => '/p/h/photo.jpg', 'types' => []],
        ];

        $plan = GalleryPlan::plan($existing, [self::A]);

        self::assertTrue($plan['changed']);
        self::assertSame([1, 3], $plan['keep']);
        self::assertSame([], $plan['add']);
    }

    public function testANewFirstImageDoesNotTakeRolesFromAnAdminImage(): void
    {
        $existing = [['id' => 3, 'file' => '/p/h/photo.jpg', 'types' => ['image']]];

        $plan = GalleryPlan::plan($existing, [self::A]);

        self::assertSame([['url' => self::A, 'position' => 1, 'roles' => []]], $plan['add']);
    }
}
