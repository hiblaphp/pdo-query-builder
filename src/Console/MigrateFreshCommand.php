<?php

declare(strict_types=1);

namespace Hibla\PdoQueryBuilder\Console;

use Hibla\PdoQueryBuilder\Console\Traits\LoadsSchemaConfiguration;
use Hibla\PdoQueryBuilder\DB;
use Rcalicdan\ConfigLoader\Config;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class MigrateFreshCommand extends Command
{
    use LoadsSchemaConfiguration;

    private SymfonyStyle $io;
    private OutputInterface $output;
    private ?string $projectRoot = null;
    private string $driver;
    private ?string $connection = null;

    protected function configure(): void
    {
        $this
            ->setName('migrate:fresh')
            ->setDescription('Drop all tables and re-run all migrations')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force the operation without confirmation')
            ->addOption('connection', null, InputOption::VALUE_OPTIONAL, 'The database connection to use')
            ->addOption('path', null, InputOption::VALUE_OPTIONAL, 'The path to migrations files')
            ->addOption('seed', null, InputOption::VALUE_NONE, 'Run seeders after migrations')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->output = $output;
        $this->io->title('Fresh Migration');

        $connectionOption = $input->getOption('connection');
        $this->connection = (is_string($connectionOption) && $connectionOption !== '') ? $connectionOption : null;

        if ($this->connection !== null) {
            $this->io->note("Using database connection: {$this->connection}");
        }

        $force = (bool) $input->getOption('force');

        if (! $force && ! $this->confirmFresh()) {
            $this->io->warning('Fresh migration cancelled');

            return Command::SUCCESS;
        }

        if (! $this->initializeProjectRoot()) {
            return Command::FAILURE;
        }

        try {
            $this->driver = $this->detectDriver();

            $this->initializeDatabase();

            $this->io->section('Dropping all tables...');
            if (! $this->dropAllTables()) {
                return Command::FAILURE;
            }

            $this->io->success('All tables dropped successfully!');

            $this->io->section('Running migrations...');

            $pathOption = $input->getOption('path');
            $path = is_string($pathOption) && $pathOption !== '' ? $pathOption : null;

            if (! $this->runMigrations($path)) {
                $this->io->error('Migration failed');

                return Command::FAILURE;
            }

            $this->io->success('Database refreshed successfully!');

            if ($input->getOption('seed')) {
                $this->io->section('Running seeders...');
                if ($this->runSeeders()) {
                    $this->io->success('Seeders completed!');
                } else {
                    $this->io->warning('Seeders not available or failed');
                }
            }

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->handleCriticalError($e);

            return Command::FAILURE;
        }
    }

    private function confirmFresh(): bool
    {
        $connectionName = $this->connection ?? $this->getDefaultConnection();
        
        $this->io->warning([
            "This will DROP ALL TABLES for connection '{$connectionName}'!",
            'All data will be permanently lost.',
        ]);

        return $this->io->confirm('Are you absolutely sure you want to continue?', false);
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

    private function dropAllTables(): bool
    {
        try {
            $migratedTables = $this->getMigratedTables();

            if (count($migratedTables) === 0) {
                $connectionName = $this->connection ?? $this->getDefaultConnection();
                $this->io->note("No migrated tables found for connection '{$connectionName}'");
                return true;
            }

            $connectionName = $this->connection ?? $this->getDefaultConnection();
            $this->io->writeln(sprintf('Found %d migrated table(s) to drop for connection: %s', 
                count($migratedTables), 
                $connectionName
            ));

            $this->disableForeignKeyChecks();

            foreach ($migratedTables as $table) {
                $this->io->write("Dropping table: {$table}...");
                $this->dropTable($table);
                $this->io->writeln(' <info>✓</info>');
            }

            $migrationsTable = $this->getMigrationsTable($this->connection);
            $this->io->write("Dropping migrations table: {$migrationsTable}...");
            $this->dropTable($migrationsTable);
            $this->io->writeln(' <info>✓</info>');

            $this->enableForeignKeyChecks();

            return true;
        } catch (\Throwable $e) {
            $this->io->error('Failed to drop tables: ' . $e->getMessage());
            if ($this->output->isVerbose()) {
                $this->io->writeln($e->getTraceAsString());
            }

            return false;
        }
    }

    /**
     * Get list of tables that were created by migrations
     * by parsing the migration files for the specific connection
     * 
     * @return list<string>
     */
    private function getMigratedTables(): array
    {
        $tables = [];
        $migrationFiles = $this->getAllMigrationFiles($this->connection);
        
        $defaultConnection = $this->getDefaultConnection();
        $targetConnection = $this->connection ?? $defaultConnection;

        foreach ($migrationFiles as $file) {
            $content = file_get_contents($file);
            if ($content === false) {
                continue;
            }

            $migrationConnection = $this->extractMigrationConnection($content);
            
            if ($migrationConnection === null) {
                $migrationConnection = $defaultConnection;
            }

            if ($migrationConnection !== $targetConnection) {
                continue;
            }

            if (preg_match_all('/->create\([\'"]([^\'"]+)[\'"]\s*,/i', $content, $matches)) {
                foreach ($matches[1] as $tableName) {
                    if (!in_array($tableName, $tables, true)) {
                        $tables[] = $tableName;
                    }
                }
            }
        }

        sort($tables);
        return $tables;
    }

    /**
     * Extract the connection property from a migration file content
     */
    private function extractMigrationConnection(string $content): ?string
    {
        if (preg_match('/protected\s+\??string\s+\$connection\s*=\s*[\'"]([^\'"]+)[\'"]/', $content, $matches)) {
            return $matches[1];
        }
        
        if (preg_match('/protected\s+\$connection\s*=\s*[\'"]([^\'"]+)[\'"]/', $content, $matches)) {
            return $matches[1];
        }
    
        return null;
    }

    /**
     * Get the default database connection name
     */
    private function getDefaultConnection(): string
    {
        try {
            $dbConfig = Config::get('pdo-query-builder');

            if (!is_array($dbConfig)) {
                return 'mysql';
            }

            $default = $dbConfig['default'] ?? 'mysql';
            
            return is_string($default) ? $default : 'mysql';
        } catch (\Throwable $e) {
            return 'mysql';
        }
    }

    private function dropTable(string $table): void
    {
        $sql = match ($this->driver) {
            'pgsql' => "DROP TABLE IF EXISTS \"{$table}\" CASCADE",
            'mysql' => "DROP TABLE IF EXISTS `{$table}`",
            'sqlite' => "DROP TABLE IF EXISTS `{$table}`",
            'sqlsrv' => "IF OBJECT_ID('{$table}', 'U') IS NOT NULL DROP TABLE [{$table}]",
            default => "DROP TABLE IF EXISTS `{$table}`",
        };

        $promise = DB::connection($this->connection)->raw($sql);
        await($promise);
    }

    /**
     * @return list<string>
     */
    private function getAllTables(): array
    {
        $databaseName = $this->getCurrentDatabase();

        $sql = match ($this->driver) {
            'mysql' => $databaseName !== null
                ? "SELECT table_name FROM information_schema.tables 
                   WHERE table_schema = '{$databaseName}' AND table_type = 'BASE TABLE'"
                : "SELECT table_name FROM information_schema.tables 
                   WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE'",
            'pgsql' => "SELECT tablename FROM pg_tables 
                       WHERE schemaname = 'public'",
            'sqlite' => "SELECT name FROM sqlite_master 
                        WHERE type='table' AND name NOT LIKE 'sqlite_%'",
            'sqlsrv' => "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES 
                        WHERE TABLE_TYPE = 'BASE TABLE'",
            default => "SELECT table_name FROM information_schema.tables 
                       WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE'",
        };

        try {
            /** @var list<array<string, mixed>> $result */
            $result = await(DB::connection($this->connection)->raw($sql));

            $columnName = match ($this->driver) {
                'pgsql' => 'tablename',
                'sqlite' => 'name',
                'sqlsrv' => 'TABLE_NAME',
                default => 'table_name',
            };

            $tables = [];
            foreach ($result as $row) {
                if (isset($row[$columnName]) && is_string($row[$columnName])) {
                    $tables[] = $row[$columnName];
                } elseif (isset($row[strtoupper($columnName)]) && is_string($row[strtoupper($columnName)])) {
                    $tables[] = $row[strtoupper($columnName)];
                }
            }

            return $tables;
        } catch (\Throwable $e) {
            if ($this->output->isVerbose()) {
                $this->io->writeln("Error fetching tables: " . $e->getMessage());
            }
            return [];
        }
    }

    /**
     * Get the current database name
     */
    private function getCurrentDatabase(): ?string
    {
        try {
            $dbConfig = Config::get('pdo-query-builder');

            if (! is_array($dbConfig)) {
                return null;
            }

            $connectionName = $this->connection ?? ($dbConfig['default'] ?? 'mysql');
            if (! is_string($connectionName)) {
                return null;
            }

            $connections = $dbConfig['connections'] ?? [];
            if (! is_array($connections)) {
                return null;
            }

            $connectionConfig = $connections[$connectionName] ?? [];
            if (! is_array($connectionConfig)) {
                return null;
            }

            $database = $connectionConfig['database'] ?? null;

            return is_string($database) ? $database : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function disableForeignKeyChecks(): void
    {
        $sql = match ($this->driver) {
            'mysql' => 'SET FOREIGN_KEY_CHECKS=0',
            'pgsql' => 'SET CONSTRAINTS ALL DEFERRED',
            'sqlite' => 'PRAGMA foreign_keys = OFF',
            'sqlsrv' => 'EXEC sp_MSforeachtable "ALTER TABLE ? NOCHECK CONSTRAINT all"',
            default => null,
        };

        if ($sql !== null) {
            try {
                $promise = DB::connection($this->connection)->raw($sql);
                await($promise);
            } catch (\Throwable $e) {
                if ($this->output->isVerbose()) {
                    $this->io->writeln("Warning: Could not disable foreign key checks: " . $e->getMessage());
                }
            }
        }
    }

    private function enableForeignKeyChecks(): void
    {
        $sql = match ($this->driver) {
            'mysql' => 'SET FOREIGN_KEY_CHECKS=1',
            'pgsql' => 'SET CONSTRAINTS ALL IMMEDIATE',
            'sqlite' => 'PRAGMA foreign_keys = ON',
            'sqlsrv' => 'EXEC sp_MSforeachtable "ALTER TABLE ? WITH CHECK CHECK CONSTRAINT all"',
            default => null,
        };

        if ($sql !== null) {
            try {
                $promise = DB::connection($this->connection)->raw($sql);
                await($promise);
            } catch (\Throwable $e) {
                if ($this->output->isVerbose()) {
                    $this->io->writeln("Warning: Could not enable foreign key checks: " . $e->getMessage());
                }
            }
        }
    }

    private function runMigrations(?string $path): bool
    {
        $application = $this->getApplication();
        if ($application === null) {
            $this->io->error('Could not find application instance.');

            return false;
        }

        $command = $application->find('migrate');

        $arguments = [];

        if ($this->connection !== null) {
            $arguments['--connection'] = $this->connection;
        }

        if ($path !== null) {
            $arguments['--path'] = $path;
        }

        $input = new ArrayInput($arguments);
        $code = $command->run($input, $this->output);

        return $code === Command::SUCCESS;
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

            if ($this->connection !== null) {
                $arguments['--connection'] = $this->connection;
            }

            $input = new ArrayInput($arguments);
            $code = $command->run($input, $this->output);

            return $code === Command::SUCCESS;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function detectDriver(): string
    {
        try {
            $dbConfig = Config::get('pdo-query-builder');

            if (! is_array($dbConfig)) {
                return 'mysql';
            }

            $connectionName = $this->connection ?? ($dbConfig['default'] ?? 'mysql');
            if (! is_string($connectionName)) {
                return 'mysql';
            }

            $connections = $dbConfig['connections'] ?? [];
            if (! is_array($connections)) {
                return 'mysql';
            }

            $connectionConfig = $connections[$connectionName] ?? [];
            if (! is_array($connectionConfig)) {
                return 'mysql';
            }

            $driver = $connectionConfig['driver'] ?? 'mysql';

            return is_string($driver) ? strtolower($driver) : 'mysql';
        } catch (\Throwable $e) {
            return 'mysql';
        }
    }

    private function initializeDatabase(): void
    {
        try {
            $testQuery = 'SELECT 1';

            await(DB::connection($this->connection)->raw($testQuery));
        } catch (\Throwable $e) {
            if ($this->output->isVerbose()) {
                $this->io->writeln("Database initialization: " . $e->getMessage());
            }
        }
    }

    private function handleCriticalError(\Throwable $e): void
    {
        $this->io->error('Fresh migration failed: ' . $e->getMessage());
        if ($this->output->isVerbose()) {
            $this->io->writeln($e->getTraceAsString());
        }
    }

    private function findProjectRoot(): ?string
    {
        $currentDir = getcwd();
        $dir = ($currentDir !== false) ? $currentDir : __DIR__;

        for ($i = 0; $i < 10; $i++) {
            if (file_exists($dir . '/composer.json')) {
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