<?php

namespace Amims71\LaraShell\Jobs;

class Job
{
    public function __construct(
        public string $id,
        public int $pid,
        public string $command,
        public string $status,
        public int $startedAt,
        public ?int $exitCode,
        public string $logPath,
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'pid' => $this->pid,
            'command' => $this->command,
            'status' => $this->status,
            'startedAt' => $this->startedAt,
            'exitCode' => $this->exitCode,
            'logPath' => $this->logPath,
        ];
    }

    public static function fromArray(array $a): self
    {
        return new self(
            id: (string) $a['id'],
            pid: (int) $a['pid'],
            command: (string) $a['command'],
            status: (string) $a['status'],
            startedAt: (int) $a['startedAt'],
            exitCode: isset($a['exitCode']) ? (int) $a['exitCode'] : null,
            logPath: (string) $a['logPath'],
        );
    }
}
