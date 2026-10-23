<?php

declare(strict_types=1);

namespace Mercur\Catalog\Model\Taxonomy;

use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Mercur\Core\Model\Api\AdminApi;

/**
 * Upserts the Magento category tree into Mercur by handle, parents before
 * children, so sellers submit products against the operator's taxonomy.
 */
class CategoryPusher
{
    /** @var array<int, string|null> magento id => mercur id, filled as the tree is walked */
    private array $mercurIds = [];

    public function __construct(
        private readonly AdminApi $api,
        private readonly CollectionFactory $categories
    ) {
    }

    public function pushAll(callable $log): int
    {
        $collection = $this->categories->create();
        $collection->addAttributeToSelect(['name', 'url_key', 'description', 'is_active', 'position'])
            ->addFieldToFilter('level', ['gt' => 1])
            ->setOrder('level', 'ASC')
            ->setOrder('position', 'ASC');

        $count = 0;
        foreach ($collection as $category) {
            $this->push($category);
            $count++;
        }
        $log("Pushed $count categor(ies)");

        return $count;
    }

    public function push(CategoryInterface $category): ?string
    {
        /** @var Category $category */
        $handle = $this->handle($category);
        $parentId = (int) $category->getParentId();
        $parentMercurId = $this->mercurIds[$parentId] ?? $this->lookup($category->getParentCategory());

        $payload = [
            'name' => (string) $category->getName(),
            'handle' => $handle,
            'description' => (string) ($category->getDescription() ?? ''),
            'is_active' => (bool) $category->getIsActive(),
            'rank' => (int) $category->getPosition(),
            'parent_category_id' => $parentMercurId,
            'metadata' => ['magento_id' => (int) $category->getId(), 'magento_path' => (string) $category->getPath()],
        ];

        $existing = $this->api->findCategoryByHandle($handle);
        $result = $existing
            ? $this->api->updateCategory((string) $existing['id'], $payload)
            : $this->api->createCategory($payload);

        $mercurId = $result['id'] ?? ($existing['id'] ?? null);
        if ($mercurId) {
            $this->mercurIds[(int) $category->getId()] = (string) $mercurId;
        }

        return $mercurId ? (string) $mercurId : null;
    }

    /**
     * The Mercur id of a category already pushed, found by the same handle the
     * push gave it; null when the category has not reached Mercur yet.
     */
    public function findMercurId(Category $category): ?string
    {
        $id = (int) $category->getId();
        if (!array_key_exists($id, $this->mercurIds)) {
            $found = $this->api->findCategoryByHandle($this->handle($category));
            $this->mercurIds[$id] = isset($found['id']) ? (string) $found['id'] : null;
        }

        return $this->mercurIds[$id];
    }

    private function lookup(?Category $parent): ?string
    {
        if ($parent === null || (int) $parent->getLevel() <= 1) {
            return null;
        }
        $found = $this->api->findCategoryByHandle($this->handle($parent));

        return $found['id'] ?? null;
    }

    private function handle(Category $category): string
    {
        $key = (string) ($category->getUrlKey() ?: $category->getName());

        return strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $key) ?? '', '-')) ?: 'category-' . $category->getId();
    }
}
