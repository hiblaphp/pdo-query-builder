<?php

declare(strict_types=1);

namespace Hibla\PdoQueryBuilder\Console;

use Hibla\PdoQueryBuilder\Console\Traits\LoadsSchemaConfiguration;
use Hibla\PdoQueryBuilder\DB;
use Hibla\PdoQueryBuilder\Schema\DatabaseManager;
use Hibla\PdoQueryBuilder\Schema\MigrationRepository;
use Hibla\PdoQueryBuilder\Schema\SchemaBuilder;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class MigrateCommand extends Command
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
            ->setName('migrate')
            ->setDescription('Run pending database migrations')
            ->addOption('step', null, InputOption::VALUE_OPTIONAL, 'Number of migrations to run', 0)
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force the operation to run');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->output = $output;
        $this->io->title('Database Migrations');

        if (!$this->initializeProjectRoot()) {
            return Command::FAILURE;
        }

        if (!$this->validateMigrationsDirectory()) {
            return Command::FAILURE;
        }

        try {
            // Automatically ensure database exists
            if (!$this->ensureDatabaseExists()) {
                return Command::FAILURE;
            }

            $this->initializeDatabase();
            $this->repository = new MigrationRepository($this->getMigrationsTable());
            $this->schema = new SchemaBuilder();

            $this->io->writeln('Preparing migration repository...');
            await($this->repository->createRepository());

            $step = (int) $input->getOption('step');
            
            if (!$this->performMigration($step)) {
                return Command::FAILURE;
            }

            $this->io->success('Migrations completed successfully!');
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->handleCriticalError($e);
            return Command::FAILURE;
        }
    }

    private function ensureDatabaseExists(): bool
    {
        try {
            $dbManager = new DatabaseManager();
            
            if (!$dbManager->databaseExists()) {
                $this->io->writeln('<comment>Database does not exist. Creating...</comment>');
                $dbManager->createDatabaseIfNotExists();
                $this->io->writeln('<info>✓ Database created successfully!</info>');
            }
            
            return true;
        } catch (\Throwable $e) {
            if ($this->isDatabaseNotExistError($e)) {
                try {
                    $this->io->writeln('<comment>Attempting to create database...</comment>');
                    $dbManager = new DatabaseManager();
                    $dbManager->createDatabaseIfNotExists();
                    $this->io->writeln('<info>✓ Database created successfully!</info>');
                    return true;
                } catch (\Throwable $createError) {
                    $this->io->error('Failed to create database: ' . $createError->getMessage());
                    if ($this->output->isVerbose()) {
                        $this->io->writeln($createError->getTraceAsString());
                    }
                    return false;
                }
            }
            
            $this->io->error('Database connection failed: ' . $e->getMessage());
            if ($this->output->isVerbose()) {
                $this->io->writeln($e->getTraceAsString());
            }
            return false;
        }
    }

    private function isDatabaseNotExistError(\Throwable $e): bool
    {
        $message = strtolower($e->getMessage());
        
        return str_contains($message, 'does not exist') ||
               str_contains($message, 'unknown database') ||
               str_contains($message, 'database') && str_contains($message, 'not found') ||
               str_contains($message, 'cannot connect to database');
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
            $this->io->error('Migrations directory not found. Run make:migration first.');
            return false;
        }
        return true;
    }

    private function performMigration(int $step): bool
    {
        $ranMigrations = await($this->repository->getRan());
        $pendingMigrations = $this->getPendingMigrations($ranMigrations);

        if (empty($pendingMigrations)) {
            $this->io->success('Nothing to migrate');
            return true;
        }

        if ($step > 0) {
            $pendingMigrations = array_slice($pendingMigrations, 0, $step);
        }

        $batchNumber = $this->getNextBatchNumber();

        $this->io->section('Running migrations');

        foreach ($pendingMigrations as $file) {
            if (!$this->runMigration($file, $batchNumber)) {
                return false;
            }
        }

        return true;
    }

    private function getPendingMigrations(array $ranMigrations): array
    {
        $files = glob($this->migrationsPath . '/*.php');
        if ($files === false) {
            return [];
        }

        sort($files);

        $ranMigrationNames = array_column($ranMigrations, 'migration');

        return array_filter($files, function ($file) use ($ranMigrationNames) {
            return !in_array(basename($file), $ranMigrationNames);
        });
    }

    private function getNextBatchNumber(): int
    {
        $batchNumber = await($this->repository->getNextBatchNumber());
        return ($batchNumber ?? 0) + 1;
    }

    private function runMigration(string $file, int $batchNumber): bool
    {
        $migrationName = basename($file);

        try {
            $migration = require $file;

            if (!$this->validateMigrationClass($migration, $migrationName)) {
                return false;
            }

            $this->io->write("Migrating: {$migrationName}...");

            await($migration->up($this->schema));
            await($this->repository->log($migrationName, $batchNumber));

            $this->io->writeln(' <info>✓</info>');
            return true;
        } catch (\Throwable $e) {
            $this->io->newLine();
            $this->io->error("Failed to run migration {$migrationName}: " . $e->getMessage());
            if ($this->output->isVerbose()) {
                $this->io->writeln($e->getTraceAsString());
            }
            return false;
        }
    }

    private function validateMigrationClass(object $migration, string $migrationName): bool
    {
        if (!method_exists($migration, 'up')) {
            $this->io->error("Migration {$migrationName} does not have an up() method");
            return false;
        }
        return true;
    }

    private function handleCriticalError(\Throwable $e): void
    {
        $this->io->error('Migration failed: ' . $e->getMessage());
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