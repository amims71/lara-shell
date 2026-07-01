<?php

namespace Amims71\LaraShell\Drivers;

use Amims71\LaraShell\Jobs\Job;
use Amims71\LaraShell\Jobs\JobRegistry;
use Amims71\LaraShell\Support\Paths;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class LocalDriver implements Driver
{
    public function __construct(
        private string $phpBinary,
        private string $artisanPath,
        private JobRegistry $registry,
        private Paths $paths,
    ) {}

    public function run(array $argv, OutputInterface $output): int
    {
        $process = new Process([$this->phpBinary, $this->artisanPath, ...$argv]);
        $process->setTimeout(0);
        $process->setIdleTimeout(null);

        $process->start(function (string $type, string $buffer) use ($output): void {
            $output->write($buffer);
        });

        return $process->wait();
    }

    /**
     * Spawn the command fully detached from this PHP process: the child's stdout/stderr
     * are appended to the job log at the OS level (shell redirect / proc_open descriptors),
     * so logging continues after this method returns and after the driver is dropped.
     * No PHP-side output pump is kept alive.
     */
    public function background(array $argv): Job
    {
        $this->paths->ensureDirectories();

        $id = bin2hex(random_bytes(4));
        $logPath = $this->paths->jobLog($id);

        $pid = PHP_OS_FAMILY === 'Windows'
            ? $this->spawnWindows($argv, $logPath)
            : $this->spawnUnix($argv, $logPath);

        $job = new Job(
            $id,
            $pid,
            implode(' ', $argv),
            'running',
            time(),
            null,
            $logPath,
        );

        $this->registry->put($job);

        return $job;
    }

    public function jobs(): JobRegistry
    {
        return $this->registry;
    }

    public function reload(): void
    {
        // No-op: LocalDriver shells out fresh subprocesses, so code is always current.
    }

    /**
     * Background the command via a shell that backgrounds ("&") the child and echoes its
     * pid ("$!"). `exec` replaces the inner shell so $! is the real php child pid. The outer
     * shell exits immediately, so we never block on the long-runner; the child, reparented
     * to init, keeps writing to the log with no PHP pump.
     */
    private function spawnUnix(array $argv, string $logPath): int
    {
        $inner = 'exec '.$this->shellCommand($argv).' >> '.escapeshellarg($logPath).' 2>&1';
        $script = $inner.' & echo $!';

        $process = Process::fromShellCommandline($script);
        $process->setTimeout(60);
        $process->run();

        $pid = (int) trim($process->getOutput());
        if ($pid <= 0) {
            throw new \RuntimeException('Unable to start background process: '.implode(' ', $argv));
        }

        return $pid;
    }

    private function spawnWindows(array $argv, string $logPath): int
    {
        $command = [$this->phpBinary, $this->artisanPath, ...$argv];

        $descriptors = [
            0 => ['file', 'NUL', 'r'],
            1 => ['file', $logPath, 'ab'],
            2 => ['file', $logPath, 'ab'],
        ];

        $handle = proc_open($command, $descriptors, $pipes, null, null, ['create_new_console' => true]);
        if (! \is_resource($handle)) {
            throw new \RuntimeException('Unable to start background process: '.implode(' ', $argv));
        }

        $status = proc_get_status($handle);
        $pid = (int) ($status['pid'] ?? 0);

        // Detach: closing the handle does not terminate a process launched in a new console.
        proc_close($handle);

        return $pid;
    }

    /** @param  string[]  $argv */
    private function shellCommand(array $argv): string
    {
        $parts = array_map(
            static fn (string $part): string => escapeshellarg($part),
            [$this->phpBinary, $this->artisanPath, ...$argv],
        );

        return implode(' ', $parts);
    }
}
