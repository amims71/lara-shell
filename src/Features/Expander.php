<?php

namespace Amims71\LaraShell\Features;

class Expander
{
    public function __construct(private AliasStore $store, private int $maxDepth = 10)
    {
    }

    /**
     * Expand ALIASES only (first word), iteratively, honoring "real command wins".
     *
     * @param  callable(string):bool  $isRealCommand
     *
     * @throws AliasLoopException on cycle or depth-cap breach
     */
    public function expand(string $line, callable $isRealCommand): string
    {
        $aliases = $this->store->aliases();

        $line = trim($line);
        $visited = [];
        $depth = 0;

        while (true) {
            [$name, $rest] = $this->split($line);

            if ($name === '') {
                return $line;
            }

            if ($isRealCommand($name)) {
                return $line;
            }

            if (! array_key_exists($name, $aliases)) {
                return $line;
            }

            if (isset($visited[$name])) {
                throw new AliasLoopException("Alias loop detected at '{$name}'.");
            }

            if ($depth >= $this->maxDepth) {
                throw new AliasLoopException("Alias expansion exceeded max depth of {$this->maxDepth}.");
            }

            $visited[$name] = true;
            $depth++;

            $expansion = trim($aliases[$name]);
            $line = $rest === '' ? $expansion : $expansion.' '.$rest;
        }
    }

    /** @return array{0:string,1:string} [firstWord, remainderOfLine] */
    private function split(string $line): array
    {
        $line = ltrim($line);

        if ($line === '') {
            return ['', ''];
        }

        $parts = preg_split('/\s+/', $line, 2);

        return [$parts[0], $parts[1] ?? ''];
    }
}
