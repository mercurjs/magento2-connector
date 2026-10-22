<?php

declare(strict_types=1);

namespace Mercur\Process\Console\Command;

use Mercur\Process\Model\HistoryCleaner;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ClearHistoryCommand extends Command
{
    public function __construct(private readonly HistoryCleaner $cleaner)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('mercur:process:clear-history')
            ->setDescription('Delete finished Mercur processes older than the configured retention')
            ->addOption('days', null, InputOption::VALUE_REQUIRED, 'Override the retention in days');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $days = $input->getOption('days');
        $result = $this->cleaner->clean($days === null ? null : max(1, (int) $days));
        $output->writeln(sprintf('<info>Deleted %d process(es) and %d related row(s).</info>', $result['processes'], $result['other']));

        return Command::SUCCESS;
    }
}
