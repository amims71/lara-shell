<?php

namespace Amims71\LaraShell\Support;

class CommandMeta
{
    /**
     * @param  string[]  $aliases
     * @param  OptionMeta[]  $options
     * @param  ArgumentMeta[]  $arguments
     */
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly array $aliases,
        public readonly bool $hidden,
        public readonly string $synopsis,
        public readonly array $options,
        public readonly array $arguments,
    ) {}
}
