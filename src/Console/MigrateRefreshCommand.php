<?php

namespace Hibla\PdoQueryBuilder\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class MigrateRefreshCommand extends Command
{
    private SymfonyStyle $io;
    private OutputInterface $output;

    protected function configure(): void
    {
        $this
            ->setName('migrate:refresh')
            ->setDescription('Reset and re-run all migrations')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->output = $output;
        $this->io->title('Refresh Migrations');

        if (! $this->resetMigrations()) {
            $this->io->error('Reset failed');

            return Command::FAILURE;
        }

        if (! $this->runMigrations()) {
            $this->io->error('Migration failed');

            return Command::FAILURE;
        }

        $this->io->success('Database refreshed successfully!');

        return Command::SUCCESS;
    }

    private function resetMigrations(): bool
    {
        return $this->executeCommand('migrate:reset');
    }

    private function runMigrations(): bool
    {
        return $this->executeCommand('migrate');
    }

    private function executeCommand(string $commandName): bool
    {
        $command = $this->getApplication()->find($commandName);
        $input = new ArrayInput([]);
        $code = $command->run($input, $this->output);

        return $code === Command::SUCCESS;
    }
}
