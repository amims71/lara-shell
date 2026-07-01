<?php

namespace Amims71\LaraShell\Jobs;

class LogTail
{
    public function __construct(private string $path) {}

    /**
     * @return array{0:string,1:int} [newBytes, newOffset]
     */
    public function read(int $offset): array
    {
        clearstatcache(true, $this->path);

        if (! is_file($this->path)) {
            return ['', 0];
        }

        $size = filesize($this->path);
        if ($size === false) {
            return ['', $offset];
        }

        if ($size < $offset) {
            $offset = 0;
        }

        if ($size === $offset) {
            return ['', $offset];
        }

        $handle = fopen($this->path, 'rb');
        if ($handle === false) {
            return ['', $offset];
        }

        try {
            if ($offset > 0) {
                fseek($handle, $offset);
            }

            $bytes = stream_get_contents($handle);
            $bytes = $bytes === false ? '' : $bytes;
            $newOffset = ftell($handle);
            $newOffset = $newOffset === false ? $offset : $newOffset;
        } finally {
            fclose($handle);
        }

        return [$bytes, $newOffset];
    }
}
