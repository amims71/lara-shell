<?php

namespace Amims71\LaraShell\Shell\Commands;

use Amims71\LaraShell\Features\AliasStore;
use Amims71\LaraShell\Features\CommandResolver;
use Psy\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AliasCommand extends Command
{
    public function __construct(private AliasStore $store, private CommandResolver $resolver)
    {
        parent::__construct('alias');
    }

    protected function configure(): void
    {
        $this->setName('alias')
            ->setDescription('Manage aliases: alias add <name> <expansion> | alias rm <name> | alias list')
            ->addArgument('args', InputArgument::IS_ARRAY, 'Subcommand and operands.');
    }

    /**
     * @param  string[]  $args
     */
    public static function apply(AliasStore $store, array $args): string
    {
        $sub = $args[0] ?? 'list';

        if ($sub === 'add') {
            $name = $args[1] ?? '';
            $expansion = implode(' ', array_slice($args, 2));
            if ($name === '' || $expansion === '') {
                return 'Usage: alias add <name> <expansion>';
            }
            $store->setAlias($name, $expansion);

            return 'Added alias '.$name.' => '.$expansion;
        }

        if ($sub === 'rm') {
            $name = $args[1] ?? '';
            if ($name === '') {
                return 'Usage: alias rm <name>';
            }
            $store->removeAlias($name);

            return 'Removed alias '.$name;
        }

        $lines = [];
        foreach ($store->aliases() as $name => $expansion) {
            $lines[] = $name.' => '.$expansion;
        }

        return $lines === [] ? 'No aliases.' : implode("\n", $lines);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $args = array_map('strval', (array) $input->getArgument('args'));
        $output->writeln(self::apply($this->store, $args));

        return 0;
    }
}
