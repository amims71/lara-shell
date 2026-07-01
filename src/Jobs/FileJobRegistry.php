<?php

namespace Amims71\LaraShell\Jobs;

class FileJobRegistry implements JobRegistry
{
    public function __construct(
        private string $jobsFile,
        private ProcessTree $tree,
    ) {}

    /** @return Job[] */
    public function all(): array
    {
        $jobs = $this->readShared();

        $changed = false;
        foreach ($jobs as $job) {
            if ($job->status === 'running' && ! $this->tree->isAlive($job->pid)) {
                $job->status = 'exited';
                $changed = true;
            }
        }

        if ($changed) {
            $this->writeAtomic($jobs);
        }

        return array_values($jobs);
    }

    public function find(string $id): ?Job
    {
        foreach ($this->all() as $job) {
            if ($job->id === $id) {
                return $job;
            }
        }

        return null;
    }

    public function put(Job $job): void
    {
        $this->mutate(function (array $jobs) use ($job): array {
            $jobs[$job->id] = $job;

            return $jobs;
        });
    }

    public function remove(string $id): void
    {
        $this->mutate(function (array $jobs) use ($id): array {
            unset($jobs[$id]);

            return $jobs;
        });
    }

    /**
     * Read-modify-write the store under an exclusive lock.
     *
     * @param  callable(array<string,Job>):array<string,Job>  $fn
     */
    private function mutate(callable $fn): void
    {
        $handle = fopen($this->jobsFile, 'c+b');
        if ($handle === false) {
            throw new \RuntimeException("Unable to open jobs file: {$this->jobsFile}");
        }

        try {
            flock($handle, LOCK_EX);

            rewind($handle);
            $raw = stream_get_contents($handle);
            $jobs = $this->decode($raw === false ? '' : $raw);

            $jobs = $fn($jobs);

            $this->writeAtomic($jobs);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /** @return array<string,Job> keyed by id */
    private function readShared(): array
    {
        if (! is_file($this->jobsFile)) {
            return [];
        }

        $handle = fopen($this->jobsFile, 'rb');
        if ($handle === false) {
            return [];
        }

        try {
            flock($handle, LOCK_SH);
            $raw = stream_get_contents($handle);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }

        return $this->decode($raw === false ? '' : $raw);
    }

    /** @return array<string,Job> keyed by id */
    private function decode(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }

        $data = json_decode($raw, true);
        if (! is_array($data)) {
            return [];
        }

        $jobs = [];
        foreach ($data as $row) {
            if (is_array($row) && isset($row['id'])) {
                $job = Job::fromArray($row);
                $jobs[$job->id] = $job;
            }
        }

        return $jobs;
    }

    /** @param  array<string,Job>  $jobs */
    private function writeAtomic(array $jobs): void
    {
        $payload = array_map(static fn (Job $job): array => $job->toArray(), array_values($jobs));
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $dir = \dirname($this->jobsFile);
        $tmp = tempnam($dir, 'jobs-');
        if ($tmp === false) {
            throw new \RuntimeException("Unable to create temp file in: {$dir}");
        }

        file_put_contents($tmp, $json);
        @chmod($tmp, 0600);

        if (! rename($tmp, $this->jobsFile)) {
            @unlink($tmp);
            throw new \RuntimeException("Unable to write jobs file: {$this->jobsFile}");
        }
    }
}
