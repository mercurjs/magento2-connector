<?php

declare(strict_types=1);

namespace Mercur\Process\Model;

use Mercur\Process\Api\TaskInterface;

class TaskPool
{
    /** @var array<string, TaskInterface> */
    private array $tasks = [];

    /**
     * @param TaskInterface[] $tasks
     */
    public function __construct(array $tasks = [])
    {
        foreach ($tasks as $task) {
            $this->tasks[$task->getCode()] = $task;
        }
    }

    public function get(string $code): TaskInterface
    {
        if (!isset($this->tasks[$code])) {
            throw new \InvalidArgumentException(sprintf('Unknown Mercur task "%s"', $code));
        }

        return $this->tasks[$code];
    }

    /**
     * @return array<string, TaskInterface>
     */
    public function all(): array
    {
        return $this->tasks;
    }
}
