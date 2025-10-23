<?php

namespace Hibla\PdoQueryBuilder\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class MigrateRefreshCommand extends Command
{
    private SymfonyStyle $io;
    private OutputInterface $output;
    private InputInterface $input;
    protected function configure(): void
    {
        $this
            ->setName('migrate:refresh')
            ->setDescription('Reset and re-run all migrations')
            ->addOption('database', null, InputOption::VALUE_OPTIONAL, 'The database connection to use')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->output = $output;
        $this->input = $input;
        $this->io->title('Refresh Migrations');

        $connectionOption = $input->getOption('database');
        $connection = (is_string($connectionOption) && $connectionOption !== '') ? $connectionOption : null;

        if ($connection !== null) {
            $this->io->note("Using database connection: {$connection}");
        }

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
        $application = $this->getApplication();
        if ($application === null) {
            $this->io->error('Application instance not found.');

            return false;
        }

        try {
            $command = $application->find($commandName);
            $arguments = [];

            $databaseOption = $this->input->getOption('database');
            if (is_string($databaseOption) && $databaseOption !== '') {
                $arguments['--database'] = $databaseOption;
            }

            $input = new ArrayInput($arguments);
            $code = $command->run($input, $this->output);

            return $code === Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->io->error("Failed to execute command '{$commandName}': " . $e->getMessage());

            return false;
        }
    }
}
