<?php

declare(strict_types=1);

namespace Mercur\Process\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Mercur\Process\Model\Process as ProcessModel;

class Process extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('mercur_process', 'id');
    }

    /**
     * Moves a runnable row to processing in one statement so two runners
     * can never pick up the same process.
     */
    public function claim(int $id, string $runnerId): bool
    {
        $now = gmdate('Y-m-d H:i:s');
        $updated = $this->getConnection()->update(
            $this->getMainTable(),
            [
                'status' => ProcessModel::STATUS_PROCESSING,
                'attempts' => new \Zend_Db_Expr('attempts + 1'),
                'locked_at' => $now,
                'locked_by' => $runnerId,
                'started_at' => $now,
                'last_output_at' => $now,
                'next_run_at' => null,
            ],
            ['id = ?' => $id, 'status IN (?)' => ProcessModel::RUNNABLE_STATUSES]
        );

        return $updated === 1;
    }

    public function appendOutput(int $id, string $chunk): void
    {
        if ($chunk === '') {
            return;
        }
        $connection = $this->getConnection();
        $connection->update(
            $this->getMainTable(),
            [
                'output' => new \Zend_Db_Expr('CONCAT(COALESCE(output, ""), ' . $connection->quote($chunk) . ')'),
                'last_output_at' => gmdate('Y-m-d H:i:s'),
            ],
            ['id = ?' => $id]
        );
    }

    /**
     * @return int[] processing rows whose lock or last output is older than their timeout
     */
    public function findStuck(int $defaultTimeoutSec): array
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable(), ['id'])
            ->where('status = ?', ProcessModel::STATUS_PROCESSING)
            ->where(
                'TIMESTAMPDIFF(SECOND, GREATEST(COALESCE(locked_at, started_at, created_at), COALESCE(last_output_at, locked_at, started_at, created_at)), ?)'
                . ' > COALESCE(timeout_sec, ' . max(1, $defaultTimeoutSec) . ')',
                gmdate('Y-m-d H:i:s')
            );

        return array_map('intval', $connection->fetchCol($select));
    }

    public function findDuplicate(string $hash): ?int
    {
        $select = $this->getConnection()->select()
            ->from($this->getMainTable(), ['id'])
            ->where('hash = ?', $hash)
            ->where('status IN (?)', ProcessModel::ACTIVE_STATUSES)
            ->order('id ASC')
            ->limit(1);
        $id = $this->getConnection()->fetchOne($select);

        return $id === false || $id === null ? null : (int) $id;
    }

    public function currentStatus(int $id): ?string
    {
        $select = $this->getConnection()->select()
            ->from($this->getMainTable(), ['status'])
            ->where('id = ?', $id);
        $status = $this->getConnection()->fetchOne($select);

        return $status === false || $status === null ? null : (string) $status;
    }

    public function markFailed(int $id, string $status, string $line): void
    {
        $this->appendOutput($id, '[' . gmdate('H:i:s') . "] $line\n");
        $this->getConnection()->update(
            $this->getMainTable(),
            ['status' => $status, 'finished_at' => gmdate('Y-m-d H:i:s'), 'locked_by' => null],
            ['id = ?' => $id, 'status = ?' => ProcessModel::STATUS_PROCESSING]
        );
    }

    /**
     * @param int[] $ids
     */
    public function setStatus(array $ids, string $status, bool $finished): int
    {
        if ($ids === []) {
            return 0;
        }
        $data = ['status' => $status];
        if ($finished) {
            $data['finished_at'] = gmdate('Y-m-d H:i:s');
            $data['locked_by'] = null;
        }

        return $this->getConnection()->update($this->getMainTable(), $data, ['id IN (?)' => $ids]);
    }

    public function deleteFinishedBefore(string $cutoff): int
    {
        return $this->getConnection()->delete($this->getMainTable(), [
            'status IN (?)' => ProcessModel::FINAL_STATUSES,
            'COALESCE(finished_at, created_at) < ?' => $cutoff,
        ]);
    }

    /**
     * @param int[] $ids
     */
    public function deleteByIds(array $ids): int
    {
        if ($ids === []) {
            return 0;
        }

        return $this->getConnection()->delete($this->getMainTable(), ['id IN (?)' => $ids]);
    }
}
