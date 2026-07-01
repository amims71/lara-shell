<?php

namespace Amims71\LaraShell\Shell;

use Closure;
use Psy\Command\Command;
use Psy\Input\CodeArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * A Psy meta-command bound to a single macro. Invoked as "@name"; its execute()
 * delegates to the injected runner (typically ArtisanShell::runMacro), which owns
 * the loop-guarded step execution.
 */
class MacroCommand extends Command
{
    /**
     * @param  Closure(string):int  $runMacro  runs the named macro, returning an exit code
     */
    public function __construct(private string $macroName, private Closure $runMacro)
    {
        parent::__construct('@'.$macroName);
    }

    protected function configure(): void
    {
        $this->setName('@'.$this->macroName)
            ->setDescription('Run the "'.$this->macroName.'" macro.')
            ->ignoreValidationErrors();

        // CodeArgument so any trailing text is captured raw instead of parsed as options.
        $this->getDefinition()->addArgument(
            new CodeArgument('args', CodeArgument::OPTIONAL, 'Ignored; macros take no arguments.')
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return ($this->runMacro)($this->macroName);
    }
}
