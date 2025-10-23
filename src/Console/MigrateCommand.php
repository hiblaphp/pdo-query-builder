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
    private SchemaBuilder $schema;
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
        $this->io = new SymfonyStyle($input, $output);
        $this->output = $output;
        $this->io->title('Database Migrations');

        $connectionOption = $input->getOption('connection');
        $this->connection = (is_string($connectionOption) && $connectionOption !== '') ? $connectionOption : null;

        if ($this->connection !== null) {
            $this->io->note("Using database connection: {$this->connection}");
        }

        if (! $this->initializeProjectRoot()) {
            return Command::FAILURE;
        }

        try {
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
            $this->schema = new SchemaBuilder(null, $this->connection);

            $this->io->writeln('Preparing migration repository...');

            $primaryRepository = $this->getRepository($this->connection);
            await($primaryRepository->createRepository());

            $stepOption = $input->getOption('step');
            $step = is_numeric($stepOption) ? (int) $stepOption : 0;

            $pathOption = $input->getOption('path');
            $path = is_string($pathOption) && $pathOption !== '' ? $pathOption : null;

            $migrationResult = $this->performMigration($step, $path);

            if ($migrationResult === false) {
                return Command::FAILURE;
            }

            if ($migrationResult === true) {
                $this->io->success('Migrations completed successfully!');
            }

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->handleCriticalError($e);

            return Command::FAILURE;
        }
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
     * Ensure database exists with user confirmation
     *
     * @return bool|null true = success, false = error, null = user declined
     */
    private function ensureDatabaseExists(bool $force): ?bool
    {
        try {
            $dbManager = new DatabaseManager($this->connection);

            if (! $dbManager->databaseExists()) {
                $dbName = $this->getDatabaseName();

                $this->io->warning("Database '{$dbName}' does not exist!");

                if (! $force) {
                    $confirmed = $this->io->confirm(
                        "Do you want to create the database '{$dbName}'?",
                        false
                    );

                    if (! $confirmed) {
                        return null;
                    }
                }

                $this->io->writeln('<comment>Creating database...</comment>');
                $dbManager->createDatabaseIfNotExists();
                $this->io->writeln('<info>✓ Database created successfully!</info>');
                $this->io->newLine();
            }

            return true;
        } catch (\Throwable $e) {
            if ($this->isDatabaseNotExistError($e)) {
                try {
                    $dbName = $this->getDatabaseName();

                    $this->io->warning("Database '{$dbName}' does not exist!");

                    if (! $force) {
                        $confirmed = $this->io->confirm(
                            "Do you want to create the database '{$dbName}'?",
                            false
                        );

                        if (! $confirmed) {
                            return null;
                        }
                    }

                    $this->io->writeln('<comment>Creating database...</comment>');
                    $dbManager = new DatabaseManager($this->connection);
                    $dbManager->createDatabaseIfNotExists();
                    $this->io->writeln('<info>✓ Database created successfully!</info>');
                    $this->io->newLine();

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

    private function getDatabaseName(): string
    {
        try {
            $dbConfig = Config::get('pdo-query-builder');

            if (! is_array($dbConfig)) {
                return 'unknown';
            }

            $connectionName = $this->connection ?? ($dbConfig['default'] ?? 'mysql');
            if (! is_string($connectionName)) {
                return 'unknown';
            }

            $connections = $dbConfig['connections'] ?? [];
            if (! is_array($connections)) {
                return 'unknown';
            }

            $config = $connections[$connectionName] ?? [];
            if (! is_array($config)) {
                return 'unknown';
            }

            $database = $config['database'] ?? 'unknown';

            return is_string($database) ? $database : 'unknown';
        } catch (\Throwable $e) {
            return 'unknown';
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

    private function performMigration(int $step, ?string $path): ?bool
    {
        $pendingMigrations = $this->getPendingMigrations($path);

        if (count($pendingMigrations) === 0) {
            $this->io->success('Nothing to migrate');

            return null;
        }

        if ($step > 0) {
            $pendingMigrations = array_slice($pendingMigrations, 0, $step);
        }

        $this->io->section('Running migrations');

        if ($path !== null) {
            $this->io->note("Running migrations from path: {$path}");
        }

        $migrationsByConnection = $this->groupMigrationsByConnection($pendingMigrations);

        foreach ($migrationsByConnection as $conn => $files) {
            $repository = $this->getRepository($conn === 'default' ? null : $conn);

            await($repository->createRepository());

            $batchNumber = await($repository->getNextBatchNumber());
            $batchNumber = (is_int($batchNumber) ? $batchNumber : 0) + 1;

            foreach ($files as $file) {
                if (! $this->runMigration($file, $batchNumber, $conn === 'default' ? null : $conn)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Get pending migrations for all connections.
     *
     * @return list<string>
     */
    private function getPendingMigrations(?string $path): array
    {
        if ($path !== null) {
            $pattern = rtrim($path, '/') . '/*.php';
            $files = $this->getFilteredMigrationFiles($pattern, null);
        } else {
            $files = $this->getAllMigrationFiles(null);
        }

        if (count($files) === 0) {
            return [];
        }

        $pending = [];

        foreach ($files as $file) {
            $migrationConnection = $this->getMigrationConnectionFromFile($file);

            $effectiveConnection = $migrationConnection;

            if ($this->connection !== null) {
                if ($migrationConnection !== $this->connection) {
                    continue;
                }
            } else {
                if ($migrationConnection !== null) {
                    continue;
                }
            }

            $relativePath = $this->getRelativeMigrationPath($file, $effectiveConnection);
            $repository = $this->getRepository($effectiveConnection);
            await($repository->createRepository());
            $ranMigrations = await($repository->getRan());
            $ranMigrationPaths = array_column($ranMigrations, 'migration');

            if (!in_array($relativePath, $ranMigrationPaths, true)) {
                $pending[] = $file;
            }
        }

        return $pending;
    }

    /**
     * Get the connection from a migration file.
     * Returns the explicit connection if set, or null if not specified.
     */
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

            if (method_exists($migration, 'getConnection')) {
                return $migration->getConnection();
            }

            $reflection = new \ReflectionObject($migration);
            if ($reflection->hasProperty('connection')) {
                $property = $reflection->getProperty('connection');
                $property->setAccessible(true);
                return $property->getValue($migration);
            }

            return null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Group migrations by their connection.
     *
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

            $migration = require $file;

            if (! is_object($migration) || ! $this->validateMigrationClass($migration, $displayName)) {
                return false;
            }

            $this->io->write("Migrating: {$displayName}");
            if ($migrationConnection !== null && $migrationConnection !== $this->connection) {
                $this->io->write(" <comment>[{$migrationConnection}]</comment>");
            }
            $this->io->write("...");

            /** @var callable(): PromiseInterface<mixed> $upMethod */
            $upMethod = [$migration, 'up'];
            $promise = $upMethod();
            await($promise);

            $repository = $this->getRepository($migrationConnection);
            await($repository->log($relativePath, $batchNumber));

            $this->io->writeln(' <info>✓</info>');

            return true;
        } catch (\Throwable $e) {
            $this->io->newLine();
            $this->io->error("Failed to run migration {$displayName}: " . $e->getMessage());
            if ($this->output->isVerbose()) {
                $this->io->writeln($e->getTraceAsString());
            }

            return false;
        }
    }

    private function validateMigrationClass(object $migration, string $migrationName): bool
    {
        if (! method_exists($migration, 'up')) {
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
