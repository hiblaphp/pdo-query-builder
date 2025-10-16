<?php

declare(strict_types=1);

namespace Hibla\PdoQueryBuilder\Console;

use Hibla\PdoQueryBuilder\DB;
use Hibla\PdoQueryBuilder\Schema\MigrationRepository;
use Hibla\PdoQueryBuilder\Schema\SchemaBuilder;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class MigrateResetCommand extends Command
{
    private SymfonyStyle $io;
    private OutputInterface $output;
    private string $projectRoot;
    private string $migrationsPath;
    private MigrationRepository $repository;
    private SchemaBuilder $schema;

    protected function configure(): void
    {
        $this
            ->setName('migrate:reset')
            ->setDescription('Rollback all database migrations');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->output = $output;
        $this->io->title('Reset All Migrations');

        if (!$this->confirmReset()) {
            $this->io->warning('Reset cancelled');
            return Command::SUCCESS;
        }

        if (!$this->initializeProjectRoot()) {
            return Command::FAILURE;
        }

        $this->migrationsPath = $this->projectRoot . '/database/migrations';

        try {
            $this->initializeDatabase();
            $this->repository = new MigrationRepository('migrations');
            $this->schema = new SchemaBuilder();

            if (!$this->performReset()) {
                return Command::FAILURE;
            }

            $this->io->success('All migrations have been reset!');
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->handleCriticalError($e);
            return Command::FAILURE;
        }
    }

    private function confirmReset(): bool
    {
        return $this->io->confirm('This will rollback ALL migrations. Are you sure?', false);
    }

    private function initializeProjectRoot(): bool
    {
        $this->projectRoot = $this->findProjectRoot();
        if (!$this->projectRoot) {
            $this->io->error('Could not find project root');
            return false;
        }
        return true;
    }

    private function performReset(): bool
    {
        $allMigrations = await($this->repository->getRan());

        if (empty($allMigrations)) {
            $this->io->warning('Nothing to reset');
            return true;
        }

        $this->io->section('Resetting all migrations');

        $allMigrations = array_reverse($allMigrations);

        foreach ($allMigrations as $migrationData) {
            $this->resetMigration($migrationData);
        }

        return true;
    }

    private function resetMigration(array $migrationData): void
    {
        $migrationName = $migrationData['migration'];
        $file = $this->migrationsPath . '/' . $migrationName;

        if (!$this->validateMigrationFile($file, $migrationName)) {
            await($this->repository->delete($migrationName));
            return;
        }

        try {
            $migration = require $file;
            
            $this->executeMigrationDown($migration, $migrationName);
            await($this->repository->delete($migrationName));
        } catch (\Throwable $e) {
            $this->handleMigrationError($migrationName, $e);
        }
    }

    private function executeMigrationDown(object $migration, string $migrationName): void
    {
        if (method_exists($migration, 'down')) {
            $this->io->write("Rolling back: {$migrationName}...");
            await($migration->down($this->schema));
            $this->io->writeln(' <info>✓</info>');
        }
    }

    private function validateMigrationFile(string $file, string $migrationName): bool
    {
        if (!file_exists($file)) {
            $this->io->warning("Migration file not found: {$migrationName}");
            return false;
        }
        return true;
    }

    private function handleMigrationError(string $migrationName, \Throwable $e): void
    {
        $this->io->newLine();
        $this->io->error("Failed to rollback {$migrationName}: " . $e->getMessage());
        if ($this->output->isVerbose()) {
            $this->io->writeln($e->getTraceAsString());
        }
    }

    private function handleCriticalError(\Throwable $e): void
    {
        $this->io->error('Reset failed: ' . $e->getMessage());
        if ($this->output->isVerbose()) {
            $this->io->writeln($e->getTraceAsString());
        }
    }

    private function initializeDatabase(): void
    {
        try {
            DB::table('_test_init');
        } catch (\Throwable $e) {
            if (!str_contains($e->getMessage(), 'not found')) {
                throw $e;
            }
        }
    }

    private function findProjectRoot(): ?string
    {
        $dir = getcwd() ?: __DIR__;
        for ($i = 0; $i < 10; $i++) {
            if (file_exists($dir . '/composer.json')) return $dir;
            $parent = dirname($dir);
            if ($parent === $dir) break;
            $dir = $parent;
        }
        return null;
    }
}