<?php

declare(strict_types=1);

namespace Hibla\PdoQueryBuilder\Console;

use Hibla\PdoQueryBuilder\Console\Traits\FindProjectRoot;
use Hibla\PdoQueryBuilder\Console\Traits\InitializeDatabase;
use Hibla\PdoQueryBuilder\Console\Traits\LoadsSchemaConfiguration;
use Hibla\PdoQueryBuilder\DB;
use Hibla\PdoQueryBuilder\Schema\DatabaseManager;
use Hibla\PdoQueryBuilder\Schema\MigrationRepository;
use Hibla\PdoQueryBuilder\Schema\SchemaBuilder;
use Hibla\Promise\Interfaces\PromiseInterface;
use Rcalicdan\ConfigLoader\Config;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class MigrateCommand extends Command
{
    use LoadsSchemaConfiguration;
    use FindProjectRoot;
    use InitializeDatabase;

    private SymfonyStyle $io;
    private OutputInterface $output;
    private ?string $projectRoot = null;
    private ?string $connection = null;
    /** @var array<string, MigrationRepository> */
    private array $repositories = [];

    protected function configure(): void
    {
        $this
            ->setName('migrate')
            ->setDescription('Run pending database migrations')
            ->addOption('step', null, InputOption::VALUE_OPTIONAL, 'Number of migrations to run', 0)
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force the operation to run without prompts')
            ->addOption('connection', null, InputOption::VALUE_OPTIONAL, 'The database connection to use')
            ->addOption('path', null, InputOption::VALUE_OPTIONAL, 'The path to migrations files (relative to migrations directory)')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initializeIo($input, $output);
        $this->io->title('Database Migrations');

        $this->setConnectionFromInput($input);

        if (!$this->initializeProjectRoot()) {
            return Command::FAILURE;
        }

        try {
            return $this->runMigrations($input);
        } catch (\Throwable $e) {
            $this->handleCriticalError($e);
            return Command::FAILURE;
        }
    }

    private function initializeIo(InputInterface $input, OutputInterface $output): void
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->output = $output;
    }

    private function setConnectionFromInput(InputInterface $input): void
    {
        $connectionOption = $input->getOption('connection');
        $this->connection = (is_string($connectionOption) && $connectionOption !== '') ? $connectionOption : null;

        if ($this->connection !== null) {
            $this->io->note("Using database connection: {$this->connection}");
        }
    }

    private function runMigrations(InputInterface $input): int
    {
        $force = (bool) $input->getOption('force');

        $dbCheckResult = $this->ensureDatabaseExists($force);

        if ($dbCheckResult === false) {
            return Command::FAILURE;
        }

        if ($dbCheckResult === null) {
            $this->io->warning('Migration cancelled by user');
            return Command::SUCCESS;
        }

        $this->initializeDatabase();
        // REMOVED: $this->schema = new SchemaBuilder(null, $this->connection);

        $this->io->writeln('Preparing migration repository...');

        $primaryRepository = $this->getRepository($this->connection);
        await($primaryRepository->createRepository());

        $step = $this->getStepOption($input);
        $path = $this->getPathOption($input);

        $migrationResult = $this->performMigration($step, $path);

        if ($migrationResult === false) {
            return Command::FAILURE;
        }

        if ($migrationResult === true) {
            $this->io->success('Migrations completed successfully!');
        }

        return Command::SUCCESS;
    }

    private function getStepOption(InputInterface $input): int
    {
        $stepOption = $input->getOption('step');
        return is_numeric($stepOption) ? (int) $stepOption : 0;
    }

    private function getPathOption(InputInterface $input): ?string
    {
        $pathOption = $input->getOption('path');
        return is_string($pathOption) && $pathOption !== '' ? $pathOption : null;
    }

    /**
     * Get or create a migration repository for a specific connection.
     */
    private function getRepository(?string $connection): MigrationRepository
    {
        $key = $connection ?? 'default';

        if (!isset($this->repositories[$key])) {
            $this->repositories[$key] = new MigrationRepository(
                $this->getMigrationsTable($connection),
                $connection
            );
        }

        return $this->repositories[$key];
    }

    /**
     * @return bool|null true = success, false = error, null = user declined
     */
    private function ensureDatabaseExists(bool $force): ?bool
    {
        try {
            $dbManager = new DatabaseManager($this->connection);

            if (!$dbManager->databaseExists()) {
                return $this->handleMissingDatabase($force);
            }

            return true;
        } catch (\Throwable $e) {
            return $this->handleDatabaseConnectionError($e, $force);
        }
    }

    private function handleMissingDatabase(bool $force): ?bool
    {
        $dbName = $this->getDatabaseName();
        $this->io->warning("Database '{$dbName}' does not exist!");

        if (!$force && !$this->confirmDatabaseCreation($dbName)) {
            return null;
        }

        return $this->createDatabase();
    }

    private function confirmDatabaseCreation(string $dbName): bool
    {
        return $this->io->confirm(
            "Do you want to create the database '{$dbName}'?",
            false
        );
    }

    private function createDatabase(): bool
    {
        try {
            $this->io->writeln('<comment>Creating database...</comment>');
            $dbManager = new DatabaseManager($this->connection);
            $dbManager->createDatabaseIfNotExists();
            $this->io->writeln('<info>✓ Database created successfully!</info>');
            $this->io->newLine();
            return true;
        } catch (\Throwable $createError) {
            $this->displayDatabaseCreationError($createError);
            return false;
        }
    }

    private function handleDatabaseConnectionError(\Throwable $e, bool $force): ?bool
    {
        if ($this->isDatabaseNotExistError($e)) {
            return $this->handleMissingDatabase($force);
        }

        $this->displayDatabaseConnectionError($e);
        return false;
    }

    private function displayDatabaseConnectionError(\Throwable $e): void
    {
        $this->io->error('Database connection failed: ' . $e->getMessage());
        if ($this->output->isVerbose()) {
            $this->io->writeln($e->getTraceAsString());
        }
    }

    private function displayDatabaseCreationError(\Throwable $e): void
    {
        $this->io->error('Failed to create database: ' . $e->getMessage());
        if ($this->output->isVerbose()) {
            $this->io->writeln($e->getTraceAsString());
        }
    }

    private function getDatabaseName(): string
    {
        try {
            $dbConfig = Config::get('pdo-query-builder');

            if (!is_array($dbConfig)) {
                return 'unknown';
            }

            /** @var array<string, mixed> $typedConfig */
            $typedConfig = $dbConfig;

            $connectionName = $this->getConnectionName($typedConfig);
            $connections = $this->getConnections($typedConfig);
            $config = $connections[$connectionName] ?? [];

            if (!is_array($config)) {
                return 'unknown';
            }

            $database = $config['database'] ?? 'unknown';
            return is_string($database) ? $database : 'unknown';
        } catch (\Throwable $e) {
            return 'unknown';
        }
    }

    /**
     * @param array<string, mixed> $dbConfig
     */
    private function getConnectionName(array $dbConfig): string
    {
        $connectionName = $this->connection ?? ($dbConfig['default'] ?? 'mysql');
        return is_string($connectionName) ? $connectionName : 'mysql';
    }

    /**
     * @param array<string, mixed> $dbConfig
     * @return array<string, mixed>
     */
    private function getConnections(array $dbConfig): array
    {
        $connections = $dbConfig['connections'] ?? [];
        
        if (!is_array($connections)) {
            return [];
        }
        
        /** @var array<string, mixed> */
        return $connections;
    }

    private function isDatabaseNotExistError(\Throwable $e): bool
    {
        $message = strtolower($e->getMessage());

        return str_contains($message, 'does not exist') ||
            str_contains($message, 'unknown database') ||
            str_contains($message, 'database') && str_contains($message, 'not found') ||
            str_contains($message, 'cannot connect to database');
    }

    private function performMigration(int $step, ?string $path): ?bool
    {
        $pendingMigrations = $this->getPendingMigrations($path);

        if (count($pendingMigrations) === 0) {
            $this->io->success('Nothing to migrate');
            return null;
        }

        $migrationsToRun = $this->limitMigrationsByStep($pendingMigrations, $step);

        $this->displayMigrationHeader($path);

        return $this->executeMigrationsByConnection($migrationsToRun);
    }

    /**
     * @param list<string> $migrations
     * @return list<string>
     */
    private function limitMigrationsByStep(array $migrations, int $step): array
    {
        if ($step > 0) {
            return array_slice($migrations, 0, $step);
        }
        return $migrations;
    }

    private function displayMigrationHeader(?string $path): void
    {
        $this->io->section('Running migrations');

        if ($path !== null) {
            $this->io->note("Running migrations from path: {$path}");
        }
    }

    /**
     * @param list<string> $migrations
     */
    private function executeMigrationsByConnection(array $migrations): bool
    {
        $migrationsByConnection = $this->groupMigrationsByConnection($migrations);

        foreach ($migrationsByConnection as $conn => $files) {
            if (!$this->executeMigrationsForConnection($conn, $files)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<string> $files
     */
    private function executeMigrationsForConnection(string $conn, array $files): bool
    {
        $connection = $conn === 'default' ? null : $conn;
        $repository = $this->getRepository($connection);

        await($repository->createRepository());

        $batchNumber = $this->getNextBatchNumber($repository);

        foreach ($files as $file) {
            if (!$this->runMigration($file, $batchNumber, $connection)) {
                return false;
            }
        }

        return true;
    }

    private function getNextBatchNumber(MigrationRepository $repository): int
    {
        $batchNumber = await($repository->getNextBatchNumber());
        return (is_int($batchNumber) ? $batchNumber : 0) + 1;
    }

    /**
     * @return list<string>
     */
    private function getPendingMigrations(?string $path): array
    {
        $files = $this->getMigrationFiles($path);

        if (count($files) === 0) {
            return [];
        }

        return $this->filterPendingMigrations($files);
    }

    /**
     * @return list<string>
     */
    private function getMigrationFiles(?string $path): array
    {
        if ($path !== null) {
            $pattern = rtrim($path, '/') . '/*.php';
            return $this->getFilteredMigrationFiles($pattern, null);
        }

        return $this->getAllMigrationFiles(null);
    }

    /**
     * @param list<string> $files
     * @return list<string>
     */
    private function filterPendingMigrations(array $files): array
    {
        $pending = [];

        foreach ($files as $file) {
            if ($this->shouldIncludeMigration($file)) {
                $pending[] = $file;
            }
        }

        return $pending;
    }

    private function shouldIncludeMigration(string $file): bool
    {
        $migrationConnection = $this->getMigrationConnectionFromFile($file);

        if (!$this->isConnectionMatching($migrationConnection)) {
            return false;
        }

        return !$this->isMigrationAlreadyRan($file, $migrationConnection);
    }

    private function isConnectionMatching(?string $migrationConnection): bool
    {
        if ($this->connection !== null) {
            return $migrationConnection === $this->connection;
        }

        return $migrationConnection === null;
    }

    private function isMigrationAlreadyRan(string $file, ?string $migrationConnection): bool
    {
        $relativePath = $this->getRelativeMigrationPath($file, $migrationConnection);
        $repository = $this->getRepository($migrationConnection);
        
        await($repository->createRepository());
        
        $ranMigrations = await($repository->getRan());
        $ranMigrationPaths = array_column($ranMigrations, 'migration');

        return in_array($relativePath, $ranMigrationPaths, true);
    }

    private function getMigrationConnectionFromFile(string $file): ?string
    {
        try {
            if (!file_exists($file)) {
                return null;
            }

            $migration = require $file;

            if (!is_object($migration)) {
                return null;
            }

            return $this->extractConnectionFromMigration($migration);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function extractConnectionFromMigration(object $migration): ?string
    {
        if (!method_exists($migration, 'getConnection')) {
            return $this->extractConnectionFromProperty($migration);
        }

        $connection = $migration->getConnection();
        return is_string($connection) ? $connection : null;
    }

    private function extractConnectionFromProperty(object $migration): ?string
    {
        try {
            $reflection = new \ReflectionObject($migration);
            
            if (!$reflection->hasProperty('connection')) {
                return null;
            }

            $property = $reflection->getProperty('connection');
            $property->setAccessible(true);
            
            $value = $property->getValue($migration);
            return is_string($value) ? $value : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * @param list<string> $files
     * @return array<string, list<string>>
     */
    private function groupMigrationsByConnection(array $files): array
    {
        $grouped = [];

        foreach ($files as $file) {
            $connection = $this->getMigrationConnectionFromFile($file);
            $key = $connection ?? 'default';

            if (!isset($grouped[$key])) {
                $grouped[$key] = [];
            }

            $grouped[$key][] = $file;
        }

        return $grouped;
    }

    private function runMigration(string $file, int $batchNumber, ?string $migrationConnection): bool
    {
        $relativePath = $this->getRelativeMigrationPath($file, $this->connection);
        $displayName = $relativePath;

        try {
            if (!file_exists($file)) {
                $this->io->error("Migration file not found: {$displayName}");
                return false;
            }

            $migration = $this->loadMigrationFile($file, $displayName);
            
            if ($migration === null) {
                return false;
            }

            $this->displayMigrationProgress($displayName, $migrationConnection);

            $this->executeMigration($migration);

            $this->logMigration($relativePath, $batchNumber, $migrationConnection);

            $this->io->writeln(' <info>✓</info>');

            return true;
        } catch (\Throwable $e) {
            $this->displayMigrationError($displayName, $e);
            return false;
        }
    }

    private function loadMigrationFile(string $file, string $displayName): ?object
    {
        $migration = require $file;

        if (!is_object($migration) || !$this->validateMigrationClass($migration, $displayName)) {
            return null;
        }

        return $migration;
    }

    private function displayMigrationProgress(string $displayName, ?string $migrationConnection): void
    {
        $this->io->write("Migrating: {$displayName}");
        
        if ($migrationConnection !== null && $migrationConnection !== $this->connection) {
            $this->io->write(" <comment>[{$migrationConnection}]</comment>");
        }
        
        $this->io->write("...");
    }

    private function executeMigration(object $migration): void
    {
        /** @var callable(): PromiseInterface<mixed> $upMethod */
        $upMethod = [$migration, 'up'];
        $promise = $upMethod();
        await($promise);
    }

    private function logMigration(string $relativePath, int $batchNumber, ?string $migrationConnection): void
    {
        $repository = $this->getRepository($migrationConnection);
        await($repository->log($relativePath, $batchNumber));
    }

    private function displayMigrationError(string $displayName, \Throwable $e): void
    {
        $this->io->newLine();
        $this->io->error("Failed to run migration {$displayName}: " . $e->getMessage());
        
        if ($this->output->isVerbose()) {
            $this->io->writeln($e->getTraceAsString());
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
}