<?php

namespace Amims71\LaraShell\Jobs;

class ProcessTree
{
    /**
     * @return int[] the pid plus every descendant pid (self first), DFS order.
     */
    public function descendants(int $pid): array
    {
        $map = $this->parsePsOutput($this->psOutput());

        $children = [];
        foreach ($map as $child => $parent) {
            $children[$parent][] = $child;
        }

        $result = [];
        $stack = [$pid];
        $seen = [];

        while ($stack !== []) {
            $current = array_pop($stack);
            if (isset($seen[$current])) {
                continue;
            }
            $seen[$current] = true;
            $result[] = $current;

            foreach ($children[$current] ?? [] as $child) {
                $stack[] = $child;
            }
        }

        return $result;
    }

    public function isAlive(int $pid): bool
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $out = [];
            exec('tasklist /FI "PID eq '.$pid.'" /NH 2>NUL', $out);

            foreach ($out as $line) {
                if (str_contains($line, (string) $pid)) {
                    return true;
                }
            }

            return false;
        }

        if (function_exists('posix_kill')) {
            if (@posix_kill($pid, 0)) {
                return true;
            }

            return posix_get_last_error() === 1; // EPERM: exists but owned by another user
        }

        $out = [];
        exec('kill -0 '.$pid.' 2>/dev/null', $out, $code);

        return $code === 0;
    }

    /**
     * SIGTERM the tree, wait $grace, SIGKILL survivors (Unix). Windows: taskkill /F /T.
     */
    public function kill(int $pid, float $grace = 2.0): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            exec('taskkill /F /T /PID '.$pid.' 2>NUL');

            return;
        }

        $pids = $this->descendants($pid);

        foreach (array_reverse($pids) as $target) {
            $this->signal($target, 15); // SIGTERM
        }

        $survivors = $pids;
        $deadline = microtime(true) + $grace;
        do {
            $survivors = array_values(array_filter($pids, fn (int $p): bool => $this->isAlive($p)));
            if ($survivors === []) {
                return;
            }
            usleep(100_000);
        } while (microtime(true) < $deadline);

        foreach (array_reverse($survivors) as $target) {
            $this->signal($target, 9); // SIGKILL
        }
    }

    /**
     * Parse `ps -eo pid=,ppid=` output into [pid => ppid]. Pure.
     *
     * @return array<int,int>
     */
    public function parsePsOutput(string $output): array
    {
        $map = [];

        foreach (preg_split('/\R/', $output) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $parts = preg_split('/\s+/', $line);
            if ($parts === false || count($parts) < 2) {
                continue;
            }

            [$pid, $ppid] = $parts;
            if (! ctype_digit($pid) || ! ctype_digit($ppid)) {
                continue;
            }

            $map[(int) $pid] = (int) $ppid;
        }

        return $map;
    }

    /**
     * Raw `ps` invocation. Overridable so tests can inject fixture output.
     */
    protected function psOutput(): string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return '';
        }

        $out = [];
        exec('ps -eo pid=,ppid= 2>/dev/null', $out);

        return implode("\n", $out);
    }

    private function signal(int $pid, int $signal): void
    {
        if (function_exists('posix_kill')) {
            @posix_kill($pid, $signal);

            return;
        }

        exec('kill -'.$signal.' '.$pid.' 2>/dev/null');
    }
}
