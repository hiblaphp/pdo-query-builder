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

class MigrateRollbackCommand extends Command
{
    use LoadsSchemaConfiguration;

    private SymfonyStyle $io;
    private OutputInterface $output;
    private ?string $projectRoot = null;
    private MigrationRepository $repository;
    private SchemaBuilder $schema;
    private ?string $connection = null;

    protected function configure(): void
    {
        $this
            ->setName('migrate:rollback')
            ->setDescription('Rollback the last database migration')
            ->addOption('step', null, InputOption::VALUE_OPTIONAL, 'Number of migrations to rollback', 1)
            ->addOption('connection', null, InputOption::VALUE_OPTIONAL, 'The database connection to use')
            ->addOption('path', null, InputOption::VALUE_OPTIONAL, 'Only rollback migrations from this path')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->output = $output;
        $this->io->title('Rollback Migrations');

        $connectionOption = $input->getOption('connection');
        $this->connection = (is_string($connectionOption) && $connectionOption !== '') ? $connectionOption : null;

        if ($this->connection !== null) {
            $this->io->note("Using database connection: {$this->connection}");
        }

        if (! $this->initializeProjectRoot()) {
            return Command::FAILURE;
        }

        try {
            $this->initializeDatabase();
            $this->repository = new MigrationRepository($this->getMigrationsTable($this->connection), $this->connection);
            $this->schema = new SchemaBuilder(null, $this->connection);

            $stepOption = $input->getOption('step');
            $step = is_numeric($stepOption) ? (int) $stepOption : 1;

            $pathOption = $input->getOption('path');
            $path = is_string($pathOption) && $pathOption !== '' ? $pathOption : null;

            $rolledBack = $this->performRollback($step, $path);

            if (! $rolledBack) {
                return Command::FAILURE;
            }

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->handleCriticalError($e);

            return Command::FAILURE;
        }
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

    private function performRollback(int $step, ?string $path): bool
    {
        /** @var list<array<string, mixed>> $ranMigrations */
        $ranMigrations = await($this->repository->getRan());

        if (count($ranMigrations) === 0) {
            $this->io->info('Nothing to rollback.');
            return true;
        }

        if ($path !== null) {
            $normalizedPath = trim($path, '/') . '/';
            $ranMigrations = array_filter($ranMigrations, function ($migration) use ($normalizedPath) {
                $migrationPath = $migration['migration'] ?? '';
                return is_string($migrationPath) && str_starts_with($migrationPath, $normalizedPath);
            });

            if (count($ranMigrations) === 0) {
                $this->io->info("No migrations to rollback in path: {$path}");
                return true;
            }

            $this->io->note("Rolling back migrations from path: {$path}");
        }

        if ($step > 0) {
            $ranMigrations = array_slice($ranMigrations, 0, $step);
        }

        $this->io->section('Rolling back migrations');

        foreach ($ranMigrations as $migrationData) {
            if (! $this->rollbackMigration($migrationData)) {
                return false;
            }
        }

        $this->io->success('Rollback completed successfully!');

        return true;
    }

    /**
     * @param array<string, mixed> $migrationData
     */
    private function rollbackMigration(array $migrationData): bool
    {
        $relativePath = $migrationData['migration'] ?? null;
        if (! is_string($relativePath)) {
            $this->io->warning('Skipping invalid migration record.');

            return false;
        }

        $file = $this->getFullMigrationPath($relativePath, $this->connection);

        if (! $this->validateMigrationFile($file, $relativePath)) {
            await($this->repository->delete($relativePath));
            $this->io->warning("Migration file not found but removed from repository: {$relativePath}");

            return true;
        }

        try {
            $migration = require $file;
            if (! is_object($migration)) {
                $this->io->error("Migration file {$relativePath} did not return an object.");

                return false;
            }

            if (! $this->validateMigrationClass($migration, $relativePath)) {
                return false;
            }

            $migrationConnection = $this->connection;
            if (method_exists($migration, 'getConnection')) {
                $declaredConnection = $migration->getConnection();
                if ($declaredConnection !== null) {
                    $migrationConnection = $declaredConnection;
                }
            }

            $this->io->write("Rolling back: {$relativePath}");
            if ($migrationConnection !== null && $migrationConnection !== $this->connection) {
                $this->io->write(" <comment>[{$migrationConnection}]</comment>");
            }
            $this->io->write("...");

            /** @var callable(): PromiseInterface<mixed> $downMethod */
            $downMethod = [$migration, 'down'];
            $promise = $downMethod();
            await($promise);

            await($this->repository->delete($relativePath));

            $this->io->writeln(' <info>✓</info>');

            return true;
        } catch (\Throwable $e) {
            $this->io->newLine();
            $this->io->error("Failed to rollback migration {$relativePath}: " . $e->getMessage());
            if ($this->output->isVerbose()) {
                $this->io->writeln($e->getTraceAsString());
            }

            return false;
        }
    }

    private function validateMigrationFile(string $file, string $migrationName): bool
    {
        return file_exists($file);
    }

    private function validateMigrationClass(object $migration, string $migrationName): bool
    {
        if (! method_exists($migration, 'down')) {
            $this->io->error("Migration {$migrationName} does not have a down() method");

            return false;
        }

        return true;
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

    private function handleCriticalError(\Throwable $e): void
    {
        $this->io->error('Rollback failed: ' . $e->getMessage());
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
