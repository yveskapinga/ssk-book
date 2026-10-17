<?php

namespace App\Command;

use App\Book\EmbeddingService;
use App\Book\RechunkEmbeddingService;
use App\Identity\User;
use App\Shared\DomainException;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:book:rechunk-embeddings',
    description: 'Rebuild finer search chunks from stored pages and re-embed with Gemini',
)]
final class RechunkEmbeddingsCommand extends Command
{
    public function __construct(
        private readonly Connection $connection,
        private readonly RechunkEmbeddingService $rechunk,
        private readonly EmbeddingService $embeddings,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('version-id', InputArgument::OPTIONAL, 'Version UUID (défaut: version courante publiée du livre KADIMA)')
            ->addOption('slug', null, InputOption::VALUE_REQUIRED, 'Slug du livre si version-id omis', 'le-prophete-kadima-bakenge')
            ->addOption('embed-only', null, InputOption::VALUE_NONE, 'Ne pas rechunker — reprendre seulement les embeddings manquants')
            ->addOption('republish', null, InputOption::VALUE_NONE, 'Remettre PUBLISHED quand embeddings = 100%');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $versionId = trim((string) $input->getArgument('version-id'));
        if ($versionId === '') {
            $slug = (string) $input->getOption('slug');
            $versionId = (string) $this->connection->fetchOne(
                'SELECT current_version_id FROM books WHERE slug = :slug AND status = \'ACTIVE\'',
                ['slug' => $slug],
            );
            if ($versionId === '') {
                $io->error('Aucune version courante pour '.$slug);

                return Command::FAILURE;
            }
        }

        $admin = $this->connection->fetchAssociative(
            <<<'SQL'
SELECT id, email, display_name, password_hash, roles, status
FROM app_users
WHERE status = 'ACTIVE' AND roles @> '["ROLE_ADMIN"]'::jsonb
ORDER BY created_at
LIMIT 1
SQL
        );
        if (false === $admin) {
            $io->error('Aucun administrateur ACTIVE pour auditer le re-embed.');

            return Command::FAILURE;
        }

        $roles = $admin['roles'];
        if (is_string($roles)) {
            $roles = json_decode($roles, true, 512, JSON_THROW_ON_ERROR);
        }
        if (!is_array($roles)) {
            $io->error('Rôles admin invalides.');

            return Command::FAILURE;
        }

        $actor = new User(
            (string) $admin['id'],
            (string) $admin['email'],
            (string) $admin['display_name'],
            (string) $admin['password_hash'],
            $roles,
            (string) $admin['status'],
        );

        try {
            if ($input->getOption('embed-only')) {
                $io->writeln('Reprise embeddings pour '.$versionId.' …');
                $guard = 0;
                $counts = ['done' => false, 'embedded' => 0, 'total' => 0];
                do {
                    try {
                        $counts = $this->embeddings->index($actor, $versionId);
                    } catch (DomainException $e) {
                        if (str_contains(mb_strtolower($e->getMessage()), 'quota')) {
                            $io->warning($e->getMessage().' Pause 180s…');
                            sleep(180);
                            continue;
                        }
                        throw $e;
                    }
                    $io->writeln(sprintf('  embedded=%s/%s', $counts['embedded'], $counts['total']));
                    ++$guard;
                } while (!(bool) ($counts['done'] ?? false) && $guard < 500);

                if ($input->getOption('republish') && (bool) ($counts['done'] ?? false)) {
                    $this->connection->executeStatement(
                        'UPDATE book_versions SET status = \'PUBLISHED\', updated_at = CURRENT_TIMESTAMP WHERE id = :id',
                        ['id' => $versionId],
                    );
                    $io->writeln('Status → PUBLISHED');
                }

                if (!(bool) ($counts['done'] ?? false)) {
                    $io->warning('Embeddings incomplets.');

                    return Command::FAILURE;
                }
                $io->success(sprintf('Embeddings OK (%s/%s).', $counts['embedded'], $counts['total']));

                return Command::SUCCESS;
            }

            $io->writeln('Rechunk + embeddings pour '.$versionId.' …');
            $result = $this->rechunk->rechunk($actor, $versionId, true);
        } catch (DomainException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->table(
            ['Champ', 'Valeur'],
            [
                ['versionId', $result['versionId']],
                ['chunks', (string) $result['chunks']],
                ['embedded', (string) $result['embedded']],
                ['total', (string) $result['total']],
                ['done', $result['done'] ? 'yes' : 'no'],
                ['previousStatus', $result['previousStatus']],
            ],
        );

        if (!$result['done']) {
            $io->warning('Embeddings incomplets — relancer avec --embed-only --republish.');

            return Command::FAILURE;
        }

        $io->success('Rechunk + embeddings terminés.');

        return Command::SUCCESS;
    }
}
