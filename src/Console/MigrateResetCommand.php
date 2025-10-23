<?php

declare(strict_types=1);

namespace Hibla\PdoQueryBuilder\Console;

use Hibla\PdoQueryBuilder\Console\Traits\LoadsSchemaConfiguration;
use Hibla\PdoQueryBuilder\DB;
use Hibla\PdoQueryBuilder\Schema\MigrationRepository;
use Hibla\PdoQueryBuilder\Schema\SchemaBuilder;
use Hibla\Promise\Interfaces\PromiseInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class MigrateResetCommand extends Command
{
    use LoadsSchemaConfiguration;

    private SymfonyStyle $io;
    private OutputInterface $output;
    private ?string $projectRoot = null;
    private string $migrationsPath;
    private MigrationRepository $repository;
    private SchemaBuilder $schema;
    private ?string $connection = null;

    protected function configure(): void
    {
        $this
            ->setName('migrate:reset')
            ->setDescription('Rollback all database migrations')
            ->addOption('connection', null, InputOption::VALUE_OPTIONAL, 'The database connection to use')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->output = $output;
        $this->io->title('Reset All Migrations');

        $connectionOption = $input->getOption('connection');
        $this->connection = (is_string($connectionOption) && $connectionOption !== '') ? $connectionOption : null;

        if ($this->connection !== null) {
            $this->io->note("Using database connection: {$this->connection}");
        }

        if (! $this->confirmReset()) {
            $this->io->warning('Reset cancelled');

            return Command::SUCCESS;
        }

        if (! $this->initializeProjectRoot()) {
            return Command::FAILURE;
        }

        $this->migrationsPath = $this->getMigrationsPath($this->connection);

        try {
            $this->initializeDatabase();
            $this->repository = new MigrationRepository($this->getMigrationsTable($this->connection), $this->connection);
            $this->schema = new SchemaBuilder(null, $this->connection);

            if (! $this->performReset()) {
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
        if ($this->projectRoot === null) {
            $this->io->error('Could not find project root');

            return false;
        }

        return true;
    }

    private function performReset(): bool
    {
        /** @var list<array<string, mixed>> $allMigrations */
        $allMigrations = await($this->repository->getRan());

        if (count($allMigrations) === 0) {
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

    /**
     * @param array<string, mixed> $migrationData
     */
    private function resetMigration(array $migrationData): void
    {
        $migrationName = $migrationData['migration'] ?? null;
        if (! is_string($migrationName)) {
            $this->io->warning('Skipping invalid migration record.');

            return;
        }

        $file = $this->migrationsPath.'/'.$migrationName;

        if (! $this->validateMigrationFile($file, $migrationName)) {
            await($this->repository->delete($migrationName));

            return;
        }

        try {
            $migration = require $file;
            if (! is_object($migration)) {
                $this->io->error("Migration file {$migrationName} did not return an object.");

                return;
            }

            $migrationConnection = $this->connection;
            if (method_exists($migration, 'getConnection')) {
                $declaredConnection = $migration->getConnection();
                if ($declaredConnection !== null) {
                    $migrationConnection = $declaredConnection;
                }
            }

            $this->executeMigrationDown($migration, $migrationName, $migrationConnection);
            await($this->repository->delete($migrationName));
        } catch (\Throwable $e) {
            $this->handleMigrationError($migrationName, $e);
        }
    }

    private function executeMigrationDown(object $migration, string $migrationName, ?string $migrationConnection): void
    {
        if (method_exists($migration, 'down')) {
            $this->io->write("Rolling back: {$migrationName}");
            
            // Show connection if different from command connection
            if ($migrationConnection !== null && $migrationConnection !== $this->connection) {
                $this->io->write(" <comment>[{$migrationConnection}]</comment>");
            }
            
            $this->io->write("...");

            /** @var callable(): PromiseInterface<mixed> $downMethod */
            $downMethod = [$migration, 'down'];
            $promise = $downMethod();
            await($promise);

            $this->io->writeln(' <info>✓</info>');
        }
    }

    private function validateMigrationFile(string $file, string $migrationName): bool
    {
        if (! file_exists($file)) {
            $this->io->warning("Migration file not found: {$migrationName}");

            return false;
        }

        return true;
    }

    private function handleMigrationError(string $migrationName, \Throwable $e): void
    {
        $this->io->newLine();
        $this->io->error("Failed to rollback {$migrationName}: ".$e->getMessage());
        if ($this->output->isVerbose()) {
            $this->io->writeln($e->getTraceAsString());
        }
    }

    private function handleCriticalError(\Throwable $e): void
    {
        $this->io->error('Reset failed: '.$e->getMessage());
        if ($this->output->isVerbose()) {
            $this->io->writeln($e->getTraceAsString());
        }
    }

    private function initializeDatabase(): void
    {
        try {
            DB::connection($this->connection)->table('_test_init');
        } catch (\Throwable $e) {
            if (! str_contains($e->getMessage(), 'not found')) {
                throw $e;
            }
        }
    }

    private function findProjectRoot(): ?string
    {
        $currentDir = getcwd();
        $dir = ($currentDir !== false) ? $currentDir : __DIR__;

        for ($i = 0; $i < 10; $i++) {
            if (file_exists($dir.'/composer.json')) {
                return $dir;
            }
            $parent = dirname($dir);
            if ($parent === $dir) {
                break;
            }
            $dir = $parent;
        }

        return null;
    }
}