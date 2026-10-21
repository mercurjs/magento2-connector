<?php

declare(strict_types=1);

namespace Mercur\Catalog\Model\Task;

use Mercur\Catalog\Model\Taxonomy\AttributePusher;
use Mercur\Catalog\Model\Taxonomy\CategoryPusher;
use Mercur\Process\Api\TaskInterface;
use Mercur\Process\Model\Process;

class TaxonomyPush implements TaskInterface
{
    public const CODE = 'taxonomy';

    public function __construct(
        private readonly CategoryPusher $categories,
        private readonly AttributePusher $attributes
    ) {
    }

    public function getCode(): string
    {
        return self::CODE;
    }

    public function getName(): string
    {
        return 'Push categories and attributes';
    }

    public function execute(Process $process, array $params): void
    {
        $log = static fn (string $line) => $process->output($line);
        $this->categories->pushAll($log);
        $this->attributes->pushAll($log);
    }
}
