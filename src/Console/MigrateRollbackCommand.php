<?php

declare(strict_types=1);

namespace Hibla\PdoQueryBuilder\Console;

use Hibla\PdoQueryBuilder\Console\Traits\LoadsSchemaConfiguration;
use Hibla\PdoQueryBuilder\DB;
use Hibla\PdoQueryBuilder\Schema\MigrationRepository;
use Hibla\PdoQueryBuilder\Schema\SchemaBuilder;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class MigrateRollbackCommand extends Command
{
    use LoadsSchemaConfiguration;

    private SymfonyStyle $io;
    private OutputInterface $output;
    private string $projectRoot;
    private string $migrationsPath;
    private MigrationRepository $repository;
    private SchemaBuilder $schema;

    protected function configure(): void
    {
        $this
            ->setName('migrate:rollback')
            ->setDescription('Rollback the last database migration')
            ->addOption('step', null, InputOption::VALUE_OPTIONAL, 'Number of migrations to rollback', 1);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->output = $output;
        $this->io->title('Rollback Migrations');

        if (!$this->initializeProjectRoot()) {
            return Command::FAILURE;
        }

        if (!$this->validateMigrationsDirectory()) {
            return Command::FAILURE;
        }

        try {
            $this->initializeDatabase();
            $this->repository = new MigrationRepository($this->getMigrationsTable());
            $this->schema = new SchemaBuilder();

            $step = (int) $input->getOption('step');
            
            if (!$this->performRollback($step)) {
                return Command::FAILURE;
            }

            $this->io->success('Rollback completed successfully!');
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->handleCriticalError($e);
            return Command::FAILURE;
        }
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

    private function validateMigrationsDirectory(): bool
    {
        $this->migrationsPath = $this->getMigrationsPath();
        if (!is_dir($this->migrationsPath)) {
            $this->io->error('Migrations directory not found');
            return false;
        }
        return true;
    }

    private function performRollback(int $step): bool
    {
        $lastBatchMigrations = await($this->repository->getLast());

        if (empty($lastBatchMigrations)) {
            $this->io->warning('Nothing to rollback');
            return true;
        }

        if ($step > 0) {
            $lastBatchMigrations = array_slice($lastBatchMigrations, 0, $step);
        }

        $this->io->section('Rolling back migrations');

        $lastBatchMigrations = array_reverse($lastBatchMigrations);

        foreach ($lastBatchMigrations as $migrationData) {
            if (!$this->rollbackMigration($migrationData)) {
                return false;
            }
        }

        return true;
    }

    private function rollbackMigration(array $migrationData): bool
    {
        $migrationName = $migrationData['migration'];
        $file = $this->migrationsPath . '/' . $migrationName;

        if (!$this->validateMigrationFile($file, $migrationName)) {
            return false;
        }

        try {
            $migration = require $file;
            
            if (!$this->validateMigrationClass($migration, $migrationName)) {
                return false;
            }

            $this->io->write("Rolling back: {$migrationName}...");
            
            await($migration->down($this->schema));
            await($this->repository->delete($migrationName));
            
            $this->io->writeln(' <info>✓</info>');
            return true;
        } catch (\Throwable $e) {
            $this->io->newLine();
            $this->io->error("Failed to rollback migration {$migrationName}: " . $e->getMessage());
            if ($this->output->isVerbose()) {
                $this->io->writeln($e->getTraceAsString());
            }
            return false;
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

    private function validateMigrationClass(object $migration, string $migrationName): bool
    {
        if (!method_exists($migration, 'down')) {
            $this->io->error("Migration {$migrationName} does not have a down() method");
            return false;
        }
        return true;
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

    private function handleCriticalError(\Throwable $e): void
    {
        $this->io->error('Rollback failed: ' . $e->getMessage());
        if ($this->output->isVerbose()) {
            $this->io->writeln($e->getTraceAsString());
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