<?php

namespace Amims71\LaraShell\Shell;

use Amims71\LaraShell\Drivers\Driver;
use Amims71\LaraShell\Features\AliasLoopException;
use Amims71\LaraShell\Features\AliasStore;
use Amims71\LaraShell\Features\CommandResolver;
use Amims71\LaraShell\Features\Expander;
use Amims71\LaraShell\Features\SafetyGuard;
use Amims71\LaraShell\Jobs\LongRunning;
use Amims71\LaraShell\Shell\Matchers\ArtisanNameMatcher;
use Amims71\LaraShell\Support\CommandCatalog;
use Closure;
use Psy\Command\Command as PsyCommand;
use Psy\Configuration;
use Psy\Shell;

class ArtisanShell extends Shell
{
    /** Default meta-command names our shell owns (used for classification). */
    private const DEFAULT_META = ['jobs', 'kill', 'logs', 'reload', 'alias', 'palette', '?', 'help', 'h', 'about', 'guide'];

    /** @var array<string,true> registered meta-command names + aliases */
    private array $metaNames = [];

    /** @var PsyCommand[] */
    private array $metaCommands = [];

    private ?Closure $lineExecutor = null;

    private int $macroMaxDepth = 10;

    private Configuration $configuration;

    public function __construct(
        Configuration $config,
        private Driver $driver,
        private CommandResolver $resolver,
        private CommandCatalog $catalog,
        private SafetyGuard $guard,
        private LongRunning $longRunning,
        private AliasStore $aliasStore,
        private Expander $expander
    ) {
        parent::__construct($config);

        $this->configuration = $config;

        foreach (self::DEFAULT_META as $name) {
            $this->metaNames[$name] = true;
        }
    }

    /**
     * Register the shell's meta-commands (built with their container deps) before setup().
     *
     * @param  PsyCommand[]  $commands
     */
    public function registerMetaCommands(array $commands): void
    {
        foreach ($commands as $command) {
            $this->metaCommands[] = $command;
            $this->metaNames[$command->getName()] = true;

            foreach ($command->getAliases() as $alias) {
                $this->metaNames[$alias] = true;
            }
        }
    }

    /** Override the executor used to run non-macro macro steps (defaults to driver dispatch). */
    public function setLineExecutor(Closure $executor): void
    {
        $this->lineExecutor = $executor;
    }

    public function setup(): void
    {
        $this->addMatchers([new ArtisanNameMatcher($this->catalog)]);

        if ($this->metaCommands !== []) {
            $this->addCommands($this->metaCommands);
        }
    }

    /**
     * @return 'php'|'artisan'|'macro'|'meta'
     */
    public function classifyInput(string $line): string
    {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, ';')) {
            return 'php';
        }

        if (str_starts_with($line, '@')) {
            return 'macro';
        }

        $first = $this->firstToken($line);

        if (isset($this->metaNames[$first])) {
            return 'meta';
        }

        return $this->resolveArtisan($line) !== null ? 'artisan' : 'php';
    }

    public function resolveArtisan(string $line): ?string
    {
        $expanded = $this->expander->expand(
            trim($line),
            fn (string $token): bool => $this->isRealCommand($token)
        );

        return $this->resolver->resolve($this->firstToken($expanded));
    }

    public function hasCommand(string $input): bool
    {
        return $this->classifyInput($input) !== 'php';
    }

    /**
     * @return PsyCommand|null
     */
    protected function getCommand(string $input)
    {
        $class = $this->classifyInput($input);

        if ($class === 'macro') {
            $name = substr($this->firstToken($input), 1);

            return new MacroCommand($name, fn (string $macro): int => $this->runMacro($macro));
        }

        if ($class === 'artisan') {
            return $this->makeDispatch($input);
        }

        if ($class === 'meta') {
            return parent::getCommand($this->firstToken($input));
        }

        return null;
    }

    public function runMacro(string $name): int
    {
        return $this->runMacroSteps($name, [], 0);
    }

    /**
     * @param  array<string,true>  $visited
     */
    private function runMacroSteps(string $name, array $visited, int $depth): int
    {
        if ($depth >= $this->macroMaxDepth || isset($visited[$name])) {
            throw new AliasLoopException('Macro loop detected at "@'.$name.'".');
        }

        $visited[$name] = true;

        $code = 0;

        foreach ($this->aliasStore->macros()[$name] ?? [] as $step) {
            $step = trim($step);

            if ($step === '') {
                continue;
            }

            if (str_starts_with($step, '@')) {
                $code = $this->runMacroSteps(substr($this->firstToken($step), 1), $visited, $depth + 1);

                continue;
            }

            $code = ($this->lineExecutor())($step);
        }

        return $code;
    }

    private function lineExecutor(): Closure
    {
        return $this->lineExecutor ??= function (string $line): int {
            if ($this->classifyInput($line) !== 'artisan') {
                return 0;
            }

            $decision = $this->makeDispatch($line)->decideStored();

            return $decision['background'] ? 0 : $this->driver->run($decision['argv'], $this->configuration->getOutput());
        };
    }

    private function makeDispatch(string $input): ArtisanDispatchCommand
    {
        $tokens = $this->expandedTokens($input);
        $name = $this->resolver->resolve($tokens[0] ?? '') ?? $this->firstToken($input);

        return new ArtisanDispatchCommand(
            $this->driver, $this->resolver, $this->guard, $this->longRunning, $name, $tokens
        );
    }

    /**
     * Expand the first-word alias, then split into argv tokens (command word + args, incl. "&").
     *
     * @return string[]
     */
    private function expandedTokens(string $input): array
    {
        $expanded = $this->expander->expand(
            trim($input),
            fn (string $token): bool => $this->isRealCommand($token)
        );

        return $this->tokenize($expanded);
    }

    private function isRealCommand(string $token): bool
    {
        return isset($this->metaNames[$token]) || $this->resolver->resolve($token) !== null;
    }

    private function firstToken(string $line): string
    {
        $parts = preg_split('/\s+/', trim($line), 2) ?: [''];

        return (string) $parts[0];
    }

    /**
     * @return string[]
     */
    private function tokenize(string $line): array
    {
        $tokens = preg_split('/\s+/', trim($line));

        return $tokens === false || $tokens === [''] ? [] : $tokens;
    }
}
