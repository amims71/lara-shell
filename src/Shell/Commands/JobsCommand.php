<?php

namespace Amims71\LaraShell\Shell\Commands;

use Amims71\LaraShell\Jobs\JobRegistry;
use Psy\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class JobsCommand extends Command
{
    public function __construct(private JobRegistry $registry)
    {
        parent::__construct('jobs');
    }

    protected function configure(): void
    {
        $this->setName('jobs')->setDescription('List background jobs across all terminals.');
    }

    /**
     * @return array<int,array{id:string,command:string,status:string,uptime:string}>
     */
    public static function rows(JobRegistry $registry): array
    {
        $now = time();

        return array_map(function ($job) use ($now): array {
            $seconds = max(0, $now - $job->startedAt);
            $minutes = intdiv($seconds, 60);
            $uptime = $minutes > 0 ? $minutes.'m'.($seconds % 60).'s' : $seconds.'s';

            return [
                'id' => $job->id,
                'command' => $job->command,
                'status' => $job->status,
                'uptime' => $uptime,
            ];
        }, array_values($registry->all()));
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $rows = self::rows($this->registry);

        if ($rows === []) {
            $output->writeln('<comment>No jobs.</comment>');

            return 0;
        }

        $table = new Table($output);
        $table->setHeaders(['ID', 'Command', 'Status', 'Uptime']);
        foreach ($rows as $row) {
            $table->addRow([$row['id'], $row['command'], $row['status'], $row['uptime']]);
        }
        $table->render();

        return 0;
    }
}
