<?php

declare(strict_types=1);

namespace Mercur\Catalog\Model\Task;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Mercur\Catalog\Model\CatalogConfig;
use Mercur\Catalog\Model\Product\Exporter;
use Mercur\Connector\Model\Sync\Cursor;
use Mercur\Process\Api\TaskInterface;
use Mercur\Process\Model\Process;

/**
 * Pushes the operator's own products to Mercur so sellers can list offers on
 * them. Products imported from Mercur (`mercur_product`) are never pushed
 * back. A child of a configurable is pushed through its parent.
 *
 * Params: `full` ignores the delta cursor, `skus` limits the push to those
 * products, `archive` moves the Mercur products behind those SKUs to draft.
 */
class ProductPush implements TaskInterface
{
    public const CODE = 'product_push';

    private const PAGE_SIZE = 200;

    public function __construct(
        private readonly CollectionFactory $collections,
        private readonly ProductRepositoryInterface $products,
        private readonly Configurable $configurableType,
        private readonly Exporter $exporter,
        private readonly Cursor $cursor,
        private readonly CatalogConfig $config
    ) {
    }

    public function getCode(): string
    {
        return self::CODE;
    }

    public function getName(): string
    {
        return 'Push operator products';
    }

    public function execute(Process $process, array $params): void
    {
        if (!$this->config->exportProducts()) {
            $process->output('Product push is disabled');

            return;
        }

        if (!empty($params['archive'])) {
            $archived = $this->exporter->archive((array) $params['archive']);
            $process->output(sprintf('Moved %d Mercur product(s) to draft', count($archived)));

            return;
        }

        $skus = array_values(array_filter(array_map('strval', (array) ($params['skus'] ?? []))));
        $since = ($params['full'] ?? false) || $skus !== [] ? null : $this->cursor->get(self::CODE);
        $process->output(match (true) {
            $skus !== [] => 'Pushing ' . implode(', ', $skus),
            $since !== null => "Delta since $since",
            default => 'Full push',
        });

        [$ids, $latest] = $this->candidates($skus, $since);
        $done = 0;
        foreach ($ids as $id) {
            try {
                $product = $this->products->getById($id, false, null, true);
                if ((int) $product->getData('mercur_product') === 1) {
                    continue;
                }
                $process->output($this->exporter->export($product));
                $done++;
            } catch (\Throwable $e) {
                $process->output(sprintf('Failed product %d: %s', $id, $e->getMessage()));
            }
        }

        if ($skus === [] && $latest !== null) {
            $this->cursor->set(self::CODE, $latest);
        }
        $process->output("Pushed $done product(s)");
    }

    /**
     * Ids of the products to push, with a configurable standing in for its
     * children, and the newest `updated_at` seen.
     *
     * @param string[] $skus
     * @return array{0: int[], 1: string|null}
     */
    private function candidates(array $skus, ?string $since): array
    {
        $collection = $this->collections->create()
            ->addAttributeToSelect('mercur_product')
            ->addAttributeToFilter('type_id', ['in' => [Type::TYPE_SIMPLE, Type::TYPE_VIRTUAL, Configurable::TYPE_CODE]])
            ->addAttributeToFilter('mercur_product', [['null' => true], ['eq' => 0]], 'left')
            ->setOrder('updated_at', 'ASC')
            ->setPageSize(self::PAGE_SIZE);
        if ($skus !== []) {
            $collection->addAttributeToFilter('sku', ['in' => $skus]);
        }
        if ($since !== null) {
            $collection->addAttributeToFilter('updated_at', ['gteq' => $since]);
        }

        $ids = [];
        $latest = null;
        $pages = $collection->getLastPageNumber();
        for ($page = 1; $page <= $pages; $page++) {
            $collection->setCurPage($page)->clear();
            foreach ($collection as $product) {
                $id = (int) $product->getId();
                if ($product->getTypeId() !== Configurable::TYPE_CODE) {
                    $parents = $this->configurableType->getParentIdsByChild($id);
                    $id = $parents !== [] ? (int) reset($parents) : $id;
                }
                $ids[$id] = $id;
                $latest = max($latest ?? '', (string) $product->getData('updated_at')) ?: $latest;
            }
        }

        return [array_values($ids), $latest];
    }
}
