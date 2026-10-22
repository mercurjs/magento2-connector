<?php

declare(strict_types=1);

namespace Mercur\Process\Console\Command;

use Mercur\Process\Model\ProcessFactory;
use Mercur\Process\Model\ResourceModel\Process as ProcessResource;
use Mercur\Process\Model\Runner;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RunProcessCommand extends Command
{
    public function __construct(
        private readonly Runner $runner,
        private readonly ProcessFactory $processFactory,
        private readonly ProcessResource $resource
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('mercur:process:run')
            ->setDescription('Run one pending Mercur process by id')
            ->addArgument('id', InputArgument::REQUIRED, 'Process id from the admin grid');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $id = (int) $input->getArgument('id');
        $process = $this->processFactory->create();
        $this->resource->load($process, $id);

        if (!$process->getId()) {
            $output->writeln(sprintf('<error>Process #%d does not exist.</error>', $id));

            return Command::FAILURE;
        }
        if (!$process->isRunnable()) {
            $output->writeln(sprintf('<error>Process #%d is %s and cannot be run.</error>', $id, $process->getStatus()));

            return Command::FAILURE;
        }
        if (!$this->runner->run($process)) {
            $output->writeln(sprintf('<error>Process #%d was claimed by another runner.</error>', $id));

            return Command::FAILURE;
        }

        $output->write((string) $process->getData('output'));
        $output->writeln(sprintf('<info>#%d %s: %s</info>', $id, $process->getCode(), $process->getStatus()));

        return $process->getStatus() === 'completed' ? Command::SUCCESS : Command::FAILURE;
    }
}
