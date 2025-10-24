<?php

declare(strict_types=1);

namespace Hibla\PdoQueryBuilder\Console;

use Hibla\PdoQueryBuilder\Console\Traits\FindProjectRoot;
use Hibla\PdoQueryBuilder\Console\Traits\InitializeDatabase;
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
    use FindProjectRoot;
    use InitializeDatabase;

    private SymfonyStyle $io;
    private OutputInterface $output;
    private ?string $projectRoot = null;
    private MigrationRepository $repository;
    private ?string $connection = null;

    protected function configure(): void
    {
        $this
            ->setName('migrate:reset')
            ->setDescription('Rollback all database migrations')
            ->addOption('connection', null, InputOption::VALUE_OPTIONAL, 'The database connection to use')
            ->addOption('path', null, InputOption::VALUE_OPTIONAL, 'Only reset migrations from this path')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Force the operation without confirmation')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initializeCommandState($input, $output);
        
        $connectionOption = $input->getOption('connection');
        $this->connection = $this->parseConnectionOption($connectionOption);
        $this->displayConnectionInfo();

        $pathOption = $input->getOption('path');
        $path = $this->parsePathOption($pathOption);
        $this->displayPathInfo($path);

        $force = $input->getOption('force') === true;

        if (!$this->shouldProceedWithReset($force, $path)) {
            $this->io->info('Operation cancelled');
            return Command::SUCCESS;
        }

        if (!$this->initializeProjectRoot()) {
            return Command::FAILURE;
        }

        try {
            $this->setupDatabaseComponents();
            $result = $this->performReset($path);
            return $this->handleResetResult($result);
        } catch (\Throwable $e) {
            $this->handleCriticalError($e);
            return Command::FAILURE;
        }
    }

    private function initializeCommandState(InputInterface $input, OutputInterface $output): void
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->output = $output;
        $this->io->title('Reset Migrations');
    }

    private function parseConnectionOption(mixed $connectionOption): ?string
    {
        return (is_string($connectionOption) && $connectionOption !== '') ? $connectionOption : null;
    }

    private function displayConnectionInfo(): void
    {
        if ($this->connection !== null) {
            $this->io->note("Using database connection: {$this->connection}");
        }
    }

    private function parsePathOption(mixed $pathOption): ?string
    {
        return is_string($pathOption) && $pathOption !== '' ? $pathOption : null;
    }

    private function displayPathInfo(?string $path): void
    {
        if ($path !== null) {
            $this->io->note("Using migration path: {$path}");
        }
    }

    private function shouldProceedWithReset(bool $force, ?string $path): bool
    {
        return $force || $this->confirmReset($path);
    }

    private function setupDatabaseComponents(): void
    {
        $this->initializeDatabase();
        $this->repository = new MigrationRepository($this->getMigrationsTable($this->connection), $this->connection);
        // REMOVED: $this->schema = new SchemaBuilder(null, $this->connection);
    }

    private function handleResetResult(int|false $result): int
    {
        if ($result === false) {
            $this->io->error('Reset failed');
            return Command::FAILURE;
        }

        if ($result === 0) {
            $this->io->info('Nothing to reset');
            return Command::SUCCESS;
        }

        $this->io->success("Successfully reset {$result} migration(s)");
        return Command::SUCCESS;
    }

    private function confirmReset(?string $path): bool
    {
        $message = $this->buildConfirmationMessage($path);
        return $this->io->confirm($message, false);
    }

    private function buildConfirmationMessage(?string $path): string
    {
        return $path !== null
            ? "This will rollback ALL migrations from path '{$path}'. Continue?"
            : 'This will rollback ALL migrations. Continue?';
    }

    private function performReset(?string $path): int|false
    {
        /** @var list<array<string, mixed>> $allMigrations */
        $allMigrations = await($this->repository->getRan());

        if (count($allMigrations) === 0) {
            return 0;
        }

        $allMigrations = $this->filterMigrations($allMigrations, $path);

        if ($allMigrations === null) {
            return 0;
        }

        $this->io->section('Rolling back migrations');

        $allMigrations = array_reverse($allMigrations);
        $resetCount = $this->rollbackMigrations($allMigrations);

        return $resetCount > 0 ? $resetCount : false;
    }

    /**
     * Filter migrations by path and connection.
     *
     * @param list<array<string, mixed>> $migrations
     * @return list<array<string, mixed>>|null
     */
    private function filterMigrations(array $migrations, ?string $path): ?array
    {
        if ($path !== null) {
            $migrations = $this->filterByPath($migrations, $path);
            if ($migrations === null) {
                return null;
            }
        }

        if ($this->connection !== null) {
            $migrations = $this->filterByConnection($migrations);
            if ($migrations === null) {
                return null;
            }
        }

        return $migrations;
    }

    /**
     * Filter migrations by path.
     *
     * @param list<array<string, mixed>> $migrations
     * @return list<array<string, mixed>>|null
     */
    private function filterByPath(array $migrations, string $path): ?array
    {
        $normalizedPath = trim($path, '/') . '/';
        $filtered = array_filter($migrations, function ($migration) use ($normalizedPath) {
            $migrationPath = $migration['migration'] ?? '';
            return is_string($migrationPath) && str_starts_with($migrationPath, $normalizedPath);
        });

        if (count($filtered) === 0) {
            $this->io->warning("No migrations found in path: {$path}");
            return null;
        }

        return array_values($filtered);
    }

    /**
     * Filter migrations by connection.
     *
     * @param list<array<string, mixed>> $migrations
     * @return list<array<string, mixed>>|null
     */
    private function filterByConnection(array $migrations): ?array
    {
        $filtered = array_filter($migrations, function ($migration) {
            return $this->migrationBelongsToConnection($migration, $this->connection);
        });

        if (count($filtered) === 0) {
            $this->io->warning("No migrations found for connection: {$this->connection}");
            return null;
        }

        return array_values($filtered);
    }

    /**
     * Rollback all migrations in the list.
     *
     * @param list<array<string, mixed>> $migrations
     */
    private function rollbackMigrations(array $migrations): int
    {
        $resetCount = 0;

        foreach ($migrations as $migrationData) {
            if ($this->resetMigration($migrationData)) {
                $resetCount++;
            }
        }

        return $resetCount;
    }

    /**
     * Check if a migration belongs to the specified connection.
     *
     * @param array<string, mixed> $migrationData
     */
    private function migrationBelongsToConnection(array $migrationData, ?string $connection): bool
    {
        $relativePath = $migrationData['migration'] ?? null;
        if (!is_string($relativePath)) {
            return false;
        }

        $file = $this->getFullMigrationPath($relativePath, null);

        if (!file_exists($file)) {
            return false;
        }

        try {
            $migration = $this->loadMigrationFile($file);
            if ($migration === null) {
                return false;
            }

            $migrationConnection = $this->extractMigrationConnection($migration);
            return $this->compareConnections($migrationConnection, $connection);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function loadMigrationFile(string $file): ?object
    {
        $migration = require $file;
        return is_object($migration) ? $migration : null;
    }

    private function extractMigrationConnection(object $migration): ?string
    {
        if (!method_exists($migration, 'getConnection')) {
            return null;
        }

        $connection = $migration->getConnection();
        return is_string($connection) ? $connection : null;
    }

    private function compareConnections(?string $migrationConnection, ?string $targetConnection): bool
    {
        if ($migrationConnection === null) {
            return $targetConnection === null;
        }

        return $migrationConnection === $targetConnection;
    }

    /**
     * @param array<string, mixed> $migrationData
     */
    private function resetMigration(array $migrationData): bool
    {
        $relativePath = $migrationData['migration'] ?? null;
        if (!is_string($relativePath)) {
            $this->io->warning('Skipping invalid migration record');
            return false;
        }

        $file = $this->getFullMigrationPath($relativePath, $this->connection);

        if (!$this->validateMigrationFile($file, $relativePath)) {
            return $this->handleMissingMigrationFile($relativePath);
        }

        try {
            return $this->executeMigrationReset($file, $relativePath);
        } catch (\Throwable $e) {
            $this->handleMigrationError($relativePath, $e);
            return false;
        }
    }

    private function handleMissingMigrationFile(string $relativePath): bool
    {
        await($this->repository->delete($relativePath));
        $this->io->warning("Migration file not found, removed from repository: {$relativePath}");
        return true;
    }

    private function executeMigrationReset(string $file, string $relativePath): bool
    {
        $migration = $this->loadMigrationFile($file);
        
        if ($migration === null) {
            $this->io->error("Migration file did not return an object: {$relativePath}");
            return false;
        }

        $migrationConnection = $this->resolveMigrationConnection($migration);
        $this->executeMigrationDown($migration, $relativePath, $migrationConnection);
        await($this->repository->delete($relativePath));

        return true;
    }

    private function resolveMigrationConnection(object $migration): ?string
    {
        $migrationConnection = $this->connection;
        
        if (!method_exists($migration, 'getConnection')) {
            return $migrationConnection;
        }

        $declaredConnection = $migration->getConnection();
        
        if (is_string($declaredConnection)) {
            return $declaredConnection;
        }

        return $migrationConnection;
    }

    private function executeMigrationDown(object $migration, string $relativePath, ?string $migrationConnection): void
    {
        if (!method_exists($migration, 'down')) {
            return;
        }

        $this->displayRollbackProgress($relativePath, $migrationConnection);
        $this->runDownMethod($migration);
        $this->displayRollbackSuccess();
    }

    private function displayRollbackProgress(string $relativePath, ?string $migrationConnection): void
    {
        $this->io->write("  Rolling back: {$relativePath}");

        if ($migrationConnection !== null && $migrationConnection !== $this->connection) {
            $this->io->write(" <comment>[{$migrationConnection}]</comment>");
        }

        $this->io->write("...");
    }

    private function runDownMethod(object $migration): void
    {
        /** @var callable(): PromiseInterface<mixed> $downMethod */
        $downMethod = [$migration, 'down'];
        $promise = $downMethod();
        await($promise);
    }

    private function displayRollbackSuccess(): void
    {
        $this->io->writeln(' <info>✓</info>');
    }

    private function validateMigrationFile(string $file, string $migrationName): bool
    {
        return file_exists($file);
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
        $this->io->error('Reset operation failed: ' . $e->getMessage());
        
        if ($this->output->isVerbose()) {
            $this->io->writeln($e->getTraceAsString());
        }
    }
}