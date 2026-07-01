<?php

namespace Amims71\LaraShell\Shell\Commands;

use Amims71\LaraShell\Support\CommandCatalog;
use Amims71\LaraShell\Support\CommandMeta;
use Psy\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class HelpCommand extends Command
{
    public function __construct(private CommandCatalog $catalog)
    {
        parent::__construct('help');
    }

    protected function configure(): void
    {
        $this->setName('help')
            ->setAliases(['h', 'about', 'guide'])
            ->setDescription('Show a guide to this shell, or usage for a specific command.')
            ->addArgument('target', InputArgument::OPTIONAL, 'A command name to show detailed usage for.');
    }

    /**
     * Ordered overview of the shell's capabilities.
     *
     * @return array<int,array{title:string,lines:string[]}>
     */
    public static function sections(): array
    {
        return [
            [
                'title' => 'Artisan commands',
                'lines' => [
                    'Type any artisan command bare, without the "php artisan" prefix.',
                    'serve            -> php artisan serve',
                    'migrate --force  -> php artisan migrate --force',
                ],
            ],
            [
                'title' => 'Fuzzy matching & palette',
                'lines' => [
                    'Command names are fuzzy-matched, so typos and abbreviations still resolve.',
                    'palette <query>  search the command palette (alias: ?).',
                    '? make           list commands matching "make".',
                ],
            ],
            [
                'title' => 'PHP eval (tinker fusion)',
                'lines' => [
                    'Anything that is not a known command is evaluated as PHP, like tinker.',
                    'User::count()    runs as PHP.',
                    'Prefix a line with ";" to force PHP even when it looks like a command.',
                    '; serve          evaluates the word "serve" as PHP instead of dispatching.',
                ],
            ],
            [
                'title' => 'Background jobs',
                'lines' => [
                    'Append "&" to run a long-running command in the background.',
                    'serve &          start the dev server as a background job.',
                    'jobs             list background jobs.',
                    'logs <id>        tail the output of a job (add -f to follow).',
                    'kill <id>        stop a background job.',
                ],
            ],
            [
                'title' => 'Aliases & macros',
                'lines' => [
                    'alias add <name> <expansion>  create a shorthand.',
                    'alias rm <name>               remove an alias.',
                    'alias list                    show all aliases.',
                    'Macros are named command sequences invoked with "@name".',
                    '@reset           run the steps saved under the "reset" macro.',
                ],
            ],
            [
                'title' => 'Production safety guard',
                'lines' => [
                    'In guarded environments (e.g. production) destructive commands are protected.',
                    'Blocked commands are refused; risky ones require confirmation before running.',
                    'This keeps commands like migrate:fresh or db:wipe from firing by accident.',
                ],
            ],
            [
                'title' => 'More',
                'lines' => [
                    'help <command>   show usage, arguments and options for a command.',
                    'reload           reload code and refresh the command catalog.',
                    'exit             leave the shell.',
                ],
            ],
        ];
    }

    /**
     * Render usage for a single command from the catalog.
     *
     * @return string[]
     */
    public static function commandHelp(CommandMeta $meta): array
    {
        $lines = [
            '<info>'.$meta->name.'</info>'.($meta->description !== '' ? '  —  '.$meta->description : ''),
            '',
            '<comment>Usage:</comment>',
            '  '.$meta->synopsis,
        ];

        if ($meta->arguments !== []) {
            $lines[] = '';
            $lines[] = '<comment>Arguments:</comment>';

            foreach ($meta->arguments as $argument) {
                $lines[] = '  '.str_pad($argument->name, 20).$argument->description;
            }
        }

        if ($meta->options !== []) {
            $lines[] = '';
            $lines[] = '<comment>Options:</comment>';

            foreach ($meta->options as $option) {
                $label = $option->name.($option->shortcut !== null ? ' (-'.$option->shortcut.')' : '');
                $lines[] = '  '.str_pad($label, 20).$option->description;
            }
        }

        return $lines;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $target = $input->getArgument('target');

        if (is_string($target) && $target !== '') {
            $meta = $this->catalog->get($target);

            if ($meta === null) {
                $output->writeln('<error>No command named "'.$target.'".</error> Try: palette '.$target);

                return 1;
            }

            foreach (self::commandHelp($meta) as $line) {
                $output->writeln($line);
            }

            return 0;
        }

        foreach (self::sections() as $section) {
            $output->writeln('<info>'.$section['title'].'</info>');

            foreach ($section['lines'] as $line) {
                $output->writeln('  '.$line);
            }

            $output->writeln('');
        }

        return 0;
    }
}
