<?php

namespace Amims71\LaraShell\Shell\Commands;

use Amims71\LaraShell\Jobs\JobRegistry;
use Amims71\LaraShell\Jobs\ProcessTree;
use Psy\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class KillCommand extends Command
{
    public function __construct(private JobRegistry $registry, private ProcessTree $tree)
    {
        parent::__construct('kill');
    }

    protected function configure(): void
    {
        $this->setName('kill')
            ->setDescription('Kill a background job and its process tree.')
            ->addArgument('id', InputArgument::REQUIRED, 'The job id to kill.');
    }

    public static function resolvePid(JobRegistry $registry, string $id): ?int
    {
        return $registry->find($id)?->pid;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $id = (string) $input->getArgument('id');
        $job = $this->registry->find($id);

        if ($job === null) {
            $output->writeln('<error>No job with id "'.$id.'".</error>');

            return 1;
        }

        $this->tree->kill($job->pid);
        $job->status = 'killed';
        $this->registry->put($job);
        $output->writeln('<info>Killed job '.$id.'.</info>');

        return 0;
    }
}
