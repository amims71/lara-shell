<?php

namespace Amims71\LaraShell\Shell\Commands;

use Amims71\LaraShell\Features\Palette;
use Psy\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PaletteCommand extends Command
{
    public function __construct(private Palette $palette)
    {
        parent::__construct('palette');
    }

    protected function configure(): void
    {
        $this->setName('palette')
            ->setAliases(['?'])
            ->setDescription('Search the command palette.')
            ->addArgument('query', InputArgument::OPTIONAL, 'Search query.', '');
    }

    /**
     * @return array<int,array{name:string,description:string,synopsis:string}>
     */
    public static function rows(Palette $palette, string $query): array
    {
        return array_map(fn ($meta): array => [
            'name' => $meta->name,
            'description' => $meta->description,
            'synopsis' => $meta->synopsis,
        ], $palette->search($query));
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $rows = self::rows($this->palette, (string) $input->getArgument('query'));

        if ($rows === []) {
            $output->writeln('<comment>No matching commands.</comment>');

            return 0;
        }

        $table = new Table($output);
        $table->setHeaders(['Command', 'Description']);
        foreach ($rows as $row) {
            $table->addRow([$row['name'], $row['description']]);
        }
        $table->render();

        return 0;
    }
}
