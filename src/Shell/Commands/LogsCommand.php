<?php

namespace Amims71\LaraShell\Shell\Commands;

use Amims71\LaraShell\Jobs\JobRegistry;
use Amims71\LaraShell\Jobs\LogTail;
use Psy\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class LogsCommand extends Command
{
    public function __construct(private JobRegistry $registry)
    {
        parent::__construct('logs');
    }

    protected function configure(): void
    {
        $this->setName('logs')
            ->setDescription('Print or follow a background job log.')
            ->addArgument('id', InputArgument::REQUIRED, 'The job id.')
            ->addOption('follow', 'f', InputOption::VALUE_NONE, 'Follow the log.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $id = (string) $input->getArgument('id');
        $job = $this->registry->find($id);

        if ($job === null) {
            $output->writeln('<error>No job with id "'.$id.'".</error>');

            return 1;
        }

        $tail = new LogTail($job->logPath);
        [$bytes, $offset] = $tail->read(0);
        if ($bytes !== '') {
            $output->write($bytes);
        }

        if (! $input->getOption('follow')) {
            return 0;
        }

        while (true) {
            usleep(200_000);
            [$chunk, $offset] = $tail->read($offset);
            if ($chunk !== '') {
                $output->write($chunk);
            }
            if ($this->registry->find($id)?->status !== 'running') {
                break;
            }
        }

        return 0;
    }
}
