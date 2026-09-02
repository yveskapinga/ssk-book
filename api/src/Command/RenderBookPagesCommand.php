<?php

namespace App\Command;

use App\Book\PageImageService;
use App\Identity\User;
use App\Identity\UserRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:book:render-pages', description: 'Extract embedded book photos for a version')]
final class RenderBookPagesCommand extends Command
{
    public function __construct(private readonly PageImageService $images, private readonly UserRepository $users)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('version-id', InputArgument::REQUIRED);
        $this->addArgument('admin-email', InputArgument::OPTIONAL, 'Compte audité', 'yveskapinga@gmail.com');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = mb_strtolower(trim((string) $input->getArgument('admin-email')));
        $actor = $this->users->findByEmail($email);
        if (!$actor instanceof User) {
            $io->error('Administrateur introuvable pour l’audit.');

            return Command::FAILURE;
        }
        $result = $this->images->render($actor, (string) $input->getArgument('version-id'));
        $io->success(sprintf('%d photos du livre extraites.', $result['images']));

        return Command::SUCCESS;
    }
}
