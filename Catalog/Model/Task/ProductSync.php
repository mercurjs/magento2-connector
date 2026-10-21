<?php

declare(strict_types=1);

namespace Mercur\Catalog\Model\Task;

use Mercur\Catalog\Model\CatalogConfig;
use Mercur\Catalog\Model\Product\Importer;
use Mercur\Connector\Model\Sync\Cursor;
use Mercur\Connector\Model\Sync\Pager;
use Mercur\Core\Model\Api\AdminApi;
use Mercur\Process\Api\TaskInterface;
use Mercur\Process\Model\Process;

class ProductSync implements TaskInterface
{
    public const CODE = 'products';

    public function __construct(
        private readonly AdminApi $api,
        private readonly Pager $pager,
        private readonly Cursor $cursor,
        private readonly Importer $importer,
        private readonly CatalogConfig $config
    ) {
    }

    public function getCode(): string
    {
        return self::CODE;
    }

    public function getName(): string
    {
        return 'Import published seller products';
    }

    public function execute(Process $process, array $params): void
    {
        if (!$this->config->importProducts()) {
            $process->output('Product import is disabled');

            return;
        }

        $since = ($params['full'] ?? false) ? null : $this->cursor->get(self::CODE);
        $process->output($since ? "Delta since $since" : 'Full import');

        $count = 0;
        $latest = $this->pager->walk(
            fn (int $offset) => $this->api->listPublishedProducts($since, $offset),
            'products',
            function (array $rows) use (&$count, $process): void {
                foreach ($rows as $product) {
                    try {
                        $skus = $this->importer->import($product);
                        $count += count($skus);
                    } catch (\Throwable $e) {
                        $process->output(sprintf('Skipped %s: %s', $product['id'] ?? '?', $e->getMessage()));
                    }
                }
            }
        );

        if ($latest) {
            $this->cursor->set(self::CODE, $latest);
        }
        $process->output("Imported $count variant(s)");
    }
}
