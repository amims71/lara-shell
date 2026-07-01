<?php

namespace Amims71\LaraShell\Shell;

use Closure;
use Psy\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
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
            ->addArgument('args', InputArgument::IS_ARRAY, 'Ignored; macros take no arguments.')
            ->ignoreValidationErrors();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return ($this->runMacro)($this->macroName);
    }
}
