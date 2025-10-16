<?php

declare(strict_types=1);

namespace Hibla\PdoQueryBuilder\Console;

use Hibla\PdoQueryBuilder\Console\Traits\LoadsSchemaConfiguration;
use Hibla\PdoQueryBuilder\DB;
use Hibla\PdoQueryBuilder\Schema\SchemaBuilder;
use Hibla\PdoQueryBuilder\Utilities\ConfigLoader;
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
    private string $projectRoot;
    private SchemaBuilder $schema;
    private string $driver;

    protected function configure(): void
    {
        $this
            ->setName('migrate:fresh')
            ->setDescription('Drop all tables and re-run all migrations')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force the operation without confirmation');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->output = $output;
        $this->io->title('Fresh Migration');

        $force = $input->getOption('force');

        if (!$force && !$this->confirmFresh()) {
            $this->io->warning('Fresh migration cancelled');
            return Command::SUCCESS;
        }

        if (!$this->initializeProjectRoot()) {
            return Command::FAILURE;
        }

        try {
            $this->initializeDatabase();
            $this->schema = new SchemaBuilder();
            $this->driver = $this->detectDriver();

            $this->io->section('Dropping all tables...');
            if (!$this->dropAllTables()) {
                return Command::FAILURE;
            }

            $this->io->success('All tables dropped successfully!');

            $this->io->section('Running migrations...');
            if (!$this->runMigrations()) {
                $this->io->error('Migration failed');
                return Command::FAILURE;
            }

            $this->io->success('Database refreshed successfully!');
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->handleCriticalError($e);
            return Command::FAILURE;
        }
    }

    private function confirmFresh(): bool
    {
        $this->io->warning([
            'This will DROP ALL TABLES in your database!',
            'All data will be permanently lost.',
        ]);

        return $this->io->confirm('Are you absolutely sure you want to continue?', false);
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

    private function dropAllTables(): bool
    {
        try {
            $tables = $this->getAllTables();

            if (empty($tables)) {
                $this->io->note('No tables to drop');
                return true;
            }

            // Disable foreign key checks
            $this->disableForeignKeyChecks();

            foreach ($tables as $table) {
                $this->io->write("Dropping table: {$table}...");
                await($this->schema->drop($table));
                $this->io->writeln(' <info>✓</info>');
            }

            // Re-enable foreign key checks
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

    private function getAllTables(): array
    {
        $sql = match ($this->driver) {
            'mysql' => "SELECT table_name FROM information_schema.tables 
                       WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE'",
            'pgsql' => "SELECT tablename FROM pg_tables 
                       WHERE schemaname = 'public'",
            'sqlite' => "SELECT name FROM sqlite_master 
                        WHERE type='table' AND name NOT LIKE 'sqlite_%'",
            'sqlsrv' => "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES 
                        WHERE TABLE_TYPE = 'BASE TABLE'",
            default => "SELECT table_name FROM information_schema.tables 
                       WHERE table_schema = DATABASE()",
        };

        $result = await(DB::raw($sql));
        
        return match ($this->driver) {
            'mysql', 'sqlsrv' => array_column($result, 'table_name'),
            'pgsql' => array_column($result, 'tablename'),
            'sqlite' => array_column($result, 'name'),
            default => array_column($result, 'table_name'),
        };
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

        if ($sql) {
            try {
                await(DB::raw($sql));
            } catch (\Throwable $e) {
                // Some drivers might not support this, continue anyway
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

        if ($sql) {
            try {
                await(DB::raw($sql));
            } catch (\Throwable $e) {
                // Some drivers might not support this, continue anyway
            }
        }
    }

    private function runMigrations(): bool
    {
        $command = $this->getApplication()->find('migrate');
        $input = new ArrayInput(['--force' => true]);
        $code = $command->run($input, $this->output);

        return $code === Command::SUCCESS;
    }

    private function detectDriver(): string
    {
        try {
            $configLoader = ConfigLoader::getInstance();
            $dbConfig = $configLoader->get('pdo-query-builder');

            if (!is_array($dbConfig)) {
                return 'mysql';
            }

            $defaultConnection = $dbConfig['default'] ?? 'mysql';
            $connections = $dbConfig['connections'] ?? [];
            $connectionConfig = $connections[$defaultConnection] ?? [];

            return strtolower($connectionConfig['driver'] ?? 'mysql');
        } catch (\Throwable $e) {
            return 'mysql';
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

    private function handleCriticalError(\Throwable $e): void
    {
        $this->io->error('Fresh migration failed: ' . $e->getMessage());
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