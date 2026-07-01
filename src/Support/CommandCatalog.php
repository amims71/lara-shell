<?php

namespace Amims71\LaraShell\Support;

use Illuminate\Contracts\Console\Kernel;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

class CommandCatalog
{
    /** @var array<string,CommandMeta>|null */
    private ?array $map = null;

    public function __construct(private Kernel $kernel) {}

    /** @return array<string,CommandMeta> keyed by canonical name */
    public function all(): array
    {
        if ($this->map === null) {
            $this->map = $this->build();
        }

        return $this->map;
    }

    /** @return string[] canonical names + aliases, sorted */
    public function names(): array
    {
        $names = [];

        foreach ($this->all() as $meta) {
            $names[] = $meta->name;

            foreach ($meta->aliases as $alias) {
                $names[] = $alias;
            }
        }

        $names = array_values(array_unique($names));
        sort($names);

        return $names;
    }

    public function get(string $name): ?CommandMeta
    {
        $all = $this->all();

        if (isset($all[$name])) {
            return $all[$name];
        }

        foreach ($all as $meta) {
            if (in_array($name, $meta->aliases, true)) {
                return $meta;
            }
        }

        return null;
    }

    public function refresh(): void
    {
        $this->map = null;
    }

    /** @return array<string,CommandMeta> */
    private function build(): array
    {
        $this->kernel->bootstrap();

        $map = [];

        foreach ($this->kernel->all() as $name => $command) {
            $map[$name] = $this->metaFor($name, $command);
        }

        return $map;
    }

    private function metaFor(string $name, SymfonyCommand $command): CommandMeta
    {
        $definition = $command->getDefinition();

        $options = [];
        foreach ($definition->getOptions() as $option) {
            $shortcut = $option->getShortcut();

            $options[] = new OptionMeta(
                '--'.$option->getName(),
                $shortcut === null ? null : (string) $shortcut,
                $option->acceptValue(),
                $option->getDescription(),
            );
        }

        $arguments = [];
        foreach ($definition->getArguments() as $argument) {
            $arguments[] = new ArgumentMeta(
                $argument->getName(),
                $argument->isRequired(),
                $argument->isArray(),
                $argument->getDescription(),
            );
        }

        return new CommandMeta(
            $name,
            $command->getDescription(),
            $command->getAliases(),
            $command->isHidden(),
            $command->getSynopsis(true),
            $options,
            $arguments,
        );
    }
}
