<?php

declare(strict_types=1);

namespace Mercur\Process\Console\Command;

use Mercur\Process\Model\Runner;
use Mercur\Process\Model\TaskPool;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class RunCommand extends Command
{
    public function __construct(
        private readonly Runner $runner,
        private readonly TaskPool $tasks
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('mercur:sync')
            ->setDescription('Run a Mercur sync task now, or every pending process when no task is given')
            ->addArgument('task', InputArgument::OPTIONAL, 'Task code: ' . implode(', ', array_keys($this->tasks->all())))
            ->addOption('full', null, InputOption::VALUE_NONE, 'Ignore the delta cursor and resync everything');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $code = $input->getArgument('task');

        if ($code === null) {
            $ran = $this->runner->runPending();
            $output->writeln(sprintf('<info>Ran %d pending process(es).</info>', $ran));

            return Command::SUCCESS;
        }

        $process = $this->runner->runNow((string) $code, ['full' => (bool) $input->getOption('full')]);
        $output->write((string) $process->getData('output'));
        $output->writeln(sprintf('<info>%s: %s</info>', $process->getCode(), $process->getStatus()));

        return $process->getStatus() === 'completed' ? Command::SUCCESS : Command::FAILURE;
    }
}
