<?php

namespace Amims71\LaraShell\Drivers;

use Amims71\LaraShell\Jobs\Job;
use Amims71\LaraShell\Jobs\JobRegistry;
use Symfony\Component\Console\Output\OutputInterface;

interface Driver
{
    /** Run a foreground artisan command; stream to $output; return exit code. $argv[0] = canonical command name. */
    public function run(array $argv, OutputInterface $output): int;

    /** Spawn a long-running command detached; register + return the Job. */
    public function background(array $argv): Job;

    public function jobs(): JobRegistry;

    /** No-op for LocalDriver. */
    public function reload(): void;
}
