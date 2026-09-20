<?php

namespace App\Command;

use App\Book\PassageIndexer;
use App\Book\BookRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:book:backfill-passages', description: 'Build sequential reading passages from extracted pages')]
final class BackfillPassagesCommand extends Command
{
    public function __construct(
        private readonly PassageIndexer $indexer,
        private readonly BookRepository $books,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('version-id', InputArgument::OPTIONAL, 'Limiter à une version');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $versionId = trim((string) $input->getArgument('version-id'));
        $ids = '' === $versionId ? $this->books->versionIdsWithPages() : [$versionId];
        if ([] === $ids) {
            $io->warning('Aucune version avec des pages à indexer.');

            return Command::SUCCESS;
        }

        $total = 0;
        foreach ($ids as $id) {
            $result = $this->indexer->rebuild((string) $id, true);
            $total += (int) $result['passages'];
            $io->writeln(sprintf('%s : %d passages (%d reprises).', $id, $result['passages'], $result['seeded']));
        }
        $io->success(sprintf('%d passages indexés sur %d version(s).', $total, count($ids)));

        return Command::SUCCESS;
    }
}
