<?php

declare(strict_types=1);

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
            ->addOption('connection', null, InputOption::VALUE_OPTIONAL, 'The database connection to use')
            ->addOption('path', null, InputOption::VALUE_OPTIONAL, 'The path to migrations files')
            ->addOption('step', null, InputOption::VALUE_OPTIONAL, 'Number of migrations to rollback')
            ->addOption('seed', null, InputOption::VALUE_NONE, 'Run seeders after migrations')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->output = $output;
        $this->input = $input;
        $this->io->title('Refresh Migrations');

        $connectionOption = $input->getOption('connection');
        $connection = (is_string($connectionOption) && $connectionOption !== '') ? $connectionOption : null;

        if ($connection !== null) {
            $this->io->note("Using database connection: {$connection}");
        }

        $pathOption = $input->getOption('path');
        $path = is_string($pathOption) && $pathOption !== '' ? $pathOption : null;

        if ($path !== null) {
            $this->io->note("Using migration path: {$path}");
        }

        $stepOption = $input->getOption('step');
        $step = is_numeric($stepOption) ? (int) $stepOption : null;

        if (! $this->resetMigrations($step, $path)) {
            $this->io->error('Reset failed');

            return Command::FAILURE;
        }

        if (! $this->runMigrations($path)) {
            $this->io->error('Migration failed');

            return Command::FAILURE;
        }

        // Run seeders if requested
        if ($input->getOption('seed')) {
            $this->io->section('Running seeders...');
            if ($this->runSeeders()) {
                $this->io->success('Seeders completed!');
            } else {
                $this->io->warning('Seeders not available or failed');
            }
        }

        $this->io->success('Database refreshed successfully!');

        return Command::SUCCESS;
    }

    private function resetMigrations(?int $step, ?string $path): bool
    {
        $commandName = $step !== null ? 'migrate:rollback' : 'migrate:reset';
        return $this->executeCommand($commandName, $step, $path);
    }

    private function runMigrations(?string $path): bool
    {
        return $this->executeCommand('migrate', null, $path);
    }

    private function runSeeders(): bool
    {
        $application = $this->getApplication();
        if ($application === null) {
            return false;
        }

        try {
            $command = $application->find('db:seed');
            $arguments = [];
            
            $connectionOption = $this->input->getOption('connection');
            if (is_string($connectionOption) && $connectionOption !== '') {
                $arguments['--connection'] = $connectionOption;
            }
            
            $input = new ArrayInput($arguments);
            $code = $command->run($input, $this->output);

            return $code === Command::SUCCESS;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function executeCommand(string $commandName, ?int $step = null, ?string $path = null): bool
    {
        $application = $this->getApplication();
        if ($application === null) {
            $this->io->error('Application instance not found.');

            return false;
        }

        try {
            $command = $application->find($commandName);
            $arguments = [];

            $connectionOption = $this->input->getOption('connection');
            if (is_string($connectionOption) && $connectionOption !== '') {
                $arguments['--connection'] = $connectionOption;
            }

            if ($path !== null) {
                $arguments['--path'] = $path;
            }

            if ($step !== null && $commandName === 'migrate:rollback') {
                $arguments['--step'] = $step;
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