<?php

declare(strict_types=1);

namespace Mercur\Process\Model;

use Magento\Framework\Lock\LockManagerInterface;
use Mercur\Process\Api\RetryLaterException;
use Mercur\Process\Model\ResourceModel\Process as ProcessResource;
use Mercur\Process\Model\ResourceModel\Process\CollectionFactory;
use Psr\Log\LoggerInterface;

class Runner
{
    public const LOCK_NAME = 'mercur_process_runner';

    private ?string $runnerId = null;

    public function __construct(
        private readonly TaskPool $tasks,
        private readonly ProcessFactory $processFactory,
        private readonly ProcessResource $resource,
        private readonly CollectionFactory $collectionFactory,
        private readonly LoggerInterface $logger,
        private readonly LockManagerInterface $lockManager,
        private readonly ProcessConfig $config,
        private readonly Backoff $backoff
    ) {
    }

    public function enqueue(string $code, array $params = [], ?int $parentId = null, ?int $maxAttempts = null): Process
    {
        $task = $this->tasks->get($code);
        $process = $this->processFactory->create();
        $process->setData('code', $code)
            ->setData('name', $task->getName())
            ->setData('status', Process::STATUS_PENDING)
            ->setData('hash', ProcessHash::compute($code, $params))
            ->setData('parent_id', $parentId)
            ->setData('attempts', 0)
            ->setData('max_attempts', $maxAttempts ?? $this->config->getMaxAttempts())
            ->setData('timeout_sec', $this->config->getTimeoutSec())
            ->setParams($params);
        $this->resource->save($process);

        return $process;
    }

    /**
     * Returns the pending, retrying or running process with the same code and
     * params when there is one, so cron ticks and webhooks never pile up copies.
     */
    public function enqueueOnce(string $code, array $params = []): Process
    {
        $existing = $this->resource->findDuplicate(ProcessHash::compute($code, $params));
        if ($existing !== null) {
            $process = $this->processFactory->create();
            $this->resource->load($process, $existing);
            if ($process->getId()) {
                return $process;
            }
        }

        return $this->enqueue($code, $params);
    }

    public function runPending(int $limit = 10): int
    {
        if (!$this->lockManager->lock(self::LOCK_NAME, 0)) {
            return 0;
        }

        try {
            $this->reapStuck();

            $collection = $this->collectionFactory->create();
            $collection->addFieldToFilter('status', ['in' => Process::RUNNABLE_STATUSES])
                ->addFieldToFilter('next_run_at', [['null' => true], ['lteq' => gmdate('Y-m-d H:i:s')]])
                ->setOrder('created_at', 'ASC')
                ->setOrder('id', 'ASC')
                ->setPageSize($limit);

            $ran = 0;
            foreach ($collection as $process) {
                if ($this->run($process)) {
                    $ran++;
                }
            }

            return $ran;
        } finally {
            $this->lockManager->unlock(self::LOCK_NAME);
        }
    }

    /**
     * @return int processes marked as timed out
     */
    public function reapStuck(): int
    {
        $ids = $this->resource->findStuck($this->config->getTimeoutSec());
        foreach ($ids as $id) {
            $this->resource->markFailed($id, Process::STATUS_TIMEOUT, 'Timed out: no activity within the process timeout');
            $this->logger->warning('Mercur process timed out', ['id' => $id]);
        }

        return count($ids);
    }

    /**
     * Runs the process when it can be claimed. Returns false when another
     * runner already took it or it is no longer runnable.
     */
    public function run(Process $process): bool
    {
        if (!$process->getId() || !$this->resource->claim((int) $process->getId(), $this->runnerId())) {
            return false;
        }
        $this->resource->load($process, (int) $process->getId());
        $this->execute($process);

        return true;
    }

    public function runNow(string $code, array $params = []): Process
    {
        $process = $this->enqueue($code, $params);
        $this->run($process);

        return $process;
    }

    public function cancel(Process $process): bool
    {
        if (!$process->getId() || $process->isFinished()) {
            return false;
        }
        $this->resource->setStatus([(int) $process->getId()], Process::STATUS_STOPPED, true);
        $process->setData('status', Process::STATUS_STOPPED);

        return true;
    }

    private function execute(Process $process): void
    {
        $id = (int) $process->getId();
        $started = microtime(true);
        $finished = false;

        // A fatal error (memory, time limit) skips every catch block below; the
        // shutdown function is the only place left to record it.
        register_shutdown_function(function () use ($id, &$finished): void {
            if ($finished) {
                return;
            }
            $error = error_get_last();
            if ($error === null || !in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
                return;
            }
            try {
                $this->resource->markFailed($id, Process::STATUS_ERROR, sprintf('Fatal: %s in %s:%d', $error['message'], $error['file'], $error['line']));
            } catch (\Throwable) {
            }
        });

        $process->setOutputFlusher(function (Process $running, string $chunk) use ($id): void {
            $this->resource->appendOutput($id, $chunk);
        });

        try {
            $this->tasks->get($process->getCode())->execute($process, $process->getParams());
            $process->setData('status', Process::STATUS_COMPLETED);
        } catch (RetryLaterException $e) {
            if ($process->canRetry()) {
                $process->output(sprintf('Retry later (%d/%d): %s', $process->getAttempts(), $process->getMaxAttempts(), $e->getMessage()))
                    ->setData('status', Process::STATUS_PENDING_RETRY)
                    ->setData('next_run_at', $this->backoff->nextRunAt($process->getAttempts(), $e->getDelaySeconds()));
            } else {
                $process->output('Error: ' . $e->getMessage() . ' (no attempts left)')
                    ->setData('status', Process::STATUS_ERROR);
            }
        } catch (\Throwable $e) {
            $process->output('Error: ' . $e->getMessage())
                ->setData('status', Process::STATUS_ERROR);
            $this->logger->error('Mercur process failed', ['code' => $process->getCode(), 'id' => $id, 'exception' => $e]);
        } finally {
            $finished = true;
            $process->setOutputFlusher(null);
            $current = $this->resource->currentStatus($id);
            if ($current !== null && in_array($current, Process::FINAL_STATUSES, true)) {
                $process->setData('status', $current);
            }
            if ($process->getStatus() !== Process::STATUS_PENDING_RETRY) {
                $process->setData('finished_at', gmdate('Y-m-d H:i:s'));
            }
            $process->setData('locked_by', null)
                ->setData('last_output_at', gmdate('Y-m-d H:i:s'))
                ->setData('duration', (int) round(microtime(true) - $started));
            $this->resource->save($process);
        }
    }

    private function runnerId(): string
    {
        return $this->runnerId ??= gethostname() . ':' . getmypid();
    }
}
