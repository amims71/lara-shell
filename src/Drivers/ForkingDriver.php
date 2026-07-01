<?php

namespace Amims71\LaraShell\Drivers;

use Amims71\LaraShell\Jobs\Job;
use Amims71\LaraShell\Jobs\JobRegistry;
use Illuminate\Contracts\Console\Kernel;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Fast Unix driver: runs foreground artisan commands by pcntl_fork()-ing the already-booted
 * shell process, so each command executes instantly (no re-boot) in a throwaway child that
 * inherits the real terminal. A crash or exit() in a command only kills its fork. Long-running
 * background jobs and the shared registry are delegated to LocalDriver.
 */
class ForkingDriver implements Driver
{
    public function __construct(
        private LocalDriver $local,
        private Kernel $kernel,
        private string $phpBinary,
        private string $artisanPath,
    ) {}

    public function run(array $argv, OutputInterface $output): int
    {
        if (! DriverFactory::supportsForking()) {
            return $this->local->run($argv, $output);
        }

        $this->resetStatefulConnections();
        $saved = $this->captureTty();

        $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        if ($pair === false) {
            return $this->local->run($argv, $output);
        }
        [$parent, $child] = $pair;

        $pid = @pcntl_fork();

        if ($pid === -1) {
            fclose($parent);
            fclose($child);

            return $this->local->run($argv, $output);
        }

        if ($pid === 0) {
            fclose($parent);
            $code = 1;

            try {
                $code = $this->handleInProcess($argv, $output);
            } catch (\Throwable $e) {
                $output->writeln('<error>'.$e->getMessage().'</error>');
            } finally {
                @fwrite($child, pack('N', $code & 0xFF));
                @fflush($child);
                // Die without shutdown handlers so shared connection fds are never closed.
                posix_kill(posix_getpid(), SIGKILL);
            }
        }

        fclose($child);
        $raw = stream_get_contents($parent);
        fclose($parent);
        pcntl_waitpid($pid, $status);
        $this->restoreTty($saved);

        return $this->resolveExitCode($raw, $status);
    }

    public function background(array $argv): Job
    {
        return $this->local->background($argv);
    }

    public function jobs(): JobRegistry
    {
        return $this->local->jobs();
    }

    public function reload(): void
    {
        if (! DriverFactory::supportsForking()) {
            $this->local->reload();

            return;
        }

        $this->restoreTty($this->captureTty());

        [$binary, $args] = $this->reloadCommand();
        pcntl_exec($binary, $args);
    }

    /** @return array{0:string,1:string[]} the re-exec target for reload (testable seam). */
    public function reloadCommand(): array
    {
        return [$this->phpBinary, [$this->artisanPath, 'shell']];
    }

    private function handleInProcess(array $argv, OutputInterface $output): int
    {
        $line = implode(' ', array_map(fn (string $t): string => $this->quote($t), $argv));

        return $this->kernel->handle(new StringInput($line), $output);
    }

    /** A command returns its code via the socketpair; a raw exit() surfaces via wait status. */
    private function resolveExitCode(string $raw, int $status): int
    {
        if (strlen($raw) >= 4) {
            return unpack('N', substr($raw, 0, 4))[1] & 0xFF;
        }

        if (pcntl_wifexited($status)) {
            return pcntl_wexitstatus($status);
        }

        return 1;
    }

    private function quote(string $token): string
    {
        return $token === '' || preg_match('/\s/', $token) === 1 ? escapeshellarg($token) : $token;
    }

    private function captureTty(): ?string
    {
        if (! @posix_isatty(STDIN)) {
            return null;
        }

        $saved = @shell_exec('stty -g < /dev/tty 2>/dev/null');

        return is_string($saved) && trim($saved) !== '' ? trim($saved) : null;
    }

    private function restoreTty(?string $saved): void
    {
        if ($saved !== null) {
            @shell_exec('stty '.escapeshellarg($saved).' < /dev/tty 2>/dev/null');
        }
    }

    /** Purge db connections before fork so the child opens its own and never shares the parent's sockets. */
    private function resetStatefulConnections(): void
    {
        $app = function_exists('app') ? app() : null;

        if ($app !== null && $app->bound('db')) {
            try {
                $app->make('db')->purge();
            } catch (\Throwable) {
                // Best-effort: a broken db manager must not block command execution.
            }
        }
    }
}
