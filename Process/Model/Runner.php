<?php

declare(strict_types=1);

namespace Mercur\Process\Model;

use Mercur\Process\Model\ResourceModel\Process as ProcessResource;
use Mercur\Process\Model\ResourceModel\Process\CollectionFactory;
use Psr\Log\LoggerInterface;

class Runner
{
    public function __construct(
        private readonly TaskPool $tasks,
        private readonly ProcessFactory $processFactory,
        private readonly ProcessResource $resource,
        private readonly CollectionFactory $collectionFactory,
        private readonly LoggerInterface $logger
    ) {
    }

    public function enqueue(string $code, array $params = []): Process
    {
        $task = $this->tasks->get($code);
        $process = $this->processFactory->create();
        $process->setData('code', $code)
            ->setData('name', $task->getName())
            ->setData('status', Process::STATUS_PENDING)
            ->setParams($params);
        $this->resource->save($process);

        return $process;
    }

    public function runPending(int $limit = 10): int
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('status', Process::STATUS_PENDING)
            ->setOrder('created_at', 'ASC')
            ->setPageSize($limit);

        $ran = 0;
        foreach ($collection as $process) {
            $this->run($process);
            $ran++;
        }

        return $ran;
    }

    public function run(Process $process): void
    {
        $started = microtime(true);
        $process->setData('status', Process::STATUS_PROCESSING)
            ->setData('started_at', gmdate('Y-m-d H:i:s'));
        $this->resource->save($process);

        try {
            $this->tasks->get($process->getCode())->execute($process, $process->getParams());
            $process->setData('status', Process::STATUS_COMPLETED);
        } catch (\Throwable $e) {
            $process->output('Error: ' . $e->getMessage())
                ->setData('status', Process::STATUS_ERROR);
            $this->logger->error('Mercur process failed', ['code' => $process->getCode(), 'exception' => $e]);
        } finally {
            $process->setData('finished_at', gmdate('Y-m-d H:i:s'))
                ->setData('duration', (int) round(microtime(true) - $started));
            $this->resource->save($process);
        }
    }

    public function runNow(string $code, array $params = []): Process
    {
        $process = $this->enqueue($code, $params);
        $this->run($process);

        return $process;
    }
}
