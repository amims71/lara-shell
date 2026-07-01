<?php

namespace Amims71\LaraShell\Shell\Commands;

use Amims71\LaraShell\Drivers\Driver;
use Amims71\LaraShell\Support\CommandCatalog;
use Psy\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ReloadCommand extends Command
{
    public function __construct(private Driver $driver, private CommandCatalog $catalog)
    {
        parent::__construct('reload');
    }

    protected function configure(): void
    {
        $this->setName('reload')->setDescription('Reload code and refresh the command catalog.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->driver->reload();
        $this->catalog->refresh();
        $output->writeln('<info>Reloaded. Command catalog refreshed.</info>');

        return 0;
    }
}
