<?php

namespace Amims71\LaraShell\Shell;

use Amims71\LaraShell\Drivers\Driver;
use Amims71\LaraShell\Features\CommandResolver;
use Amims71\LaraShell\Features\GuardLevel;
use Amims71\LaraShell\Features\SafetyGuard;
use Amims71\LaraShell\Jobs\LongRunning;
use Psy\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ArtisanDispatchCommand extends Command
{
    /**
     * @param  string[]  $tokens  the pre-tokenized argv (command word + args, incl. a trailing "&"),
     *                            supplied by ArtisanShell so we never re-parse through Symfony.
     */
    public function __construct(
        private Driver $driver,
        private CommandResolver $resolver,
        private SafetyGuard $guard,
        private LongRunning $longRunning,
        private string $canonicalName,
        private array $tokens = []
    ) {
        parent::__construct($canonicalName);
    }

    protected function configure(): void
    {
        $this->setName($this->canonicalName)
            ->setDescription('Run the artisan command "'.$this->canonicalName.'"')
            ->addArgument('args', InputArgument::IS_ARRAY, 'Arguments and options passed to the artisan command.')
            ->ignoreValidationErrors();
    }

    /**
     * Pure dispatch decision. No shell, no side effects.
     *
     * @param  string[]  $tokens  full argv incl. the command name
     * @return array{name:string,argv:string[],background:bool,level:GuardLevel}
     */
    public function decide(array $tokens): array
    {
        $tokens = array_values($tokens);
        $background = false;

        if ($tokens !== [] && end($tokens) === '&') {
            array_pop($tokens);
            $background = true;
        }

        $argv = array_values($tokens);
        $name = $this->resolver->resolve($argv[0] ?? $this->canonicalName) ?? $this->canonicalName;
        $argv[0] = $name;

        $level = $this->guard->classify($name, $argv);

        if ($this->longRunning->matches($name)) {
            $background = true;
        }

        return [
            'name' => $name,
            'argv' => $argv,
            'background' => $background,
            'level' => $level,
        ];
    }

    /** Decide from the tokens ArtisanShell handed us (falls back to the bare command name). */
    public function decideStored(): array
    {
        return $this->decide($this->tokens !== [] ? $this->tokens : [$this->canonicalName]);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $decision = $this->decideStored();

        if ($decision['level'] === GuardLevel::Block) {
            $output->writeln('<error>Command "'.$decision['name'].'" is blocked in this environment.</error>');

            return 1;
        }

        if ($decision['level'] === GuardLevel::Confirm && ! $this->confirmRetype($decision['name'], $output)) {
            $output->writeln('<error>Aborted.</error>');

            return 1;
        }

        if ($decision['background']) {
            $job = $this->driver->background($decision['argv']);
            $output->writeln('<info>Started background job '.$job->id.' ('.$job->command.')</info>');

            return 0;
        }

        return $this->driver->run($decision['argv'], $output);
    }

    private function confirmRetype(string $name, OutputInterface $output): bool
    {
        $output->writeln('<comment>This command is guarded. Re-type "'.$name.'" to proceed:</comment>');

        $answer = fgets(STDIN);

        return $answer !== false && trim($answer) === $name;
    }
}
