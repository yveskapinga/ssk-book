<?php

namespace App\Command;

use App\Identity\AuthService;
use App\Shared\DomainException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:admin:create', description: 'Create an initial SQL-first administrator account')]
final class CreateAdminCommand extends Command
{
    public function __construct(private readonly AuthService $authService)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED)
            ->addArgument('display-name', InputArgument::REQUIRED)
            ->addOption('password', null, InputOption::VALUE_REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $password = $input->getOption('password') ?: $io->askHidden('Mot de passe administrateur');

        try {
            $result = $this->authService->register(
                (string) $input->getArgument('email'),
                (string) $input->getArgument('display-name'),
                (string) $password,
                ['ROLE_USER', 'ROLE_ADMIN'],
            );
            $io->success(sprintf('Administrateur %s créé.', $result['user']['email']));

            return Command::SUCCESS;
        } catch (DomainException $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }
    }
}
