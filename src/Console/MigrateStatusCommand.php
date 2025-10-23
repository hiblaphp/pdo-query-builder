<?php

declare(strict_types=1);

namespace Hibla\PdoQueryBuilder\Console;

use Hibla\PdoQueryBuilder\Console\Traits\LoadsSchemaConfiguration;
use Hibla\PdoQueryBuilder\DB;
use Hibla\PdoQueryBuilder\Schema\MigrationRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class MigrateStatusCommand extends Command
{
    use LoadsSchemaConfiguration;

    private SymfonyStyle $io;
    private ?string $projectRoot = null;
    private ?string $connection = null;

    protected function configure(): void
    {
        $this
            ->setName('migrate:status')
            ->setDescription('Show the status of each migration')
            ->addOption('connection', null, InputOption::VALUE_OPTIONAL, 'The database connection to use')
            ->addOption('path', null, InputOption::VALUE_OPTIONAL, 'Only show migrations from this path')
            ->addOption('pending', null, InputOption::VALUE_NONE, 'Only show pending migrations')
            ->addOption('ran', null, InputOption::VALUE_NONE, 'Only show completed migrations')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->io->title('Migration Status');

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

            $pathOption = $input->getOption('path');
            $path = is_string($pathOption) && $pathOption !== '' ? $pathOption : null;

            $pendingOnly = (bool) $input->getOption('pending');
            $ranOnly = (bool) $input->getOption('ran');

            $this->displayMigrationStatus($path, $pendingOnly, $ranOnly);

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->handleError($e);

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

    private function displayMigrationStatus(?string $path, bool $pendingOnly, bool $ranOnly): void
    {
        if ($path !== null) {
            $pattern = rtrim($path, '/') . '/*.php';
            $migrationFiles = $this->getFilteredMigrationFiles($pattern, $this->connection);

            if (count($migrationFiles) === 0) {
                $this->io->warning("No migration files found in path: {$path}");
                return;
            }

            $this->io->note("Showing migrations from path: {$path}");
        } else {
            $migrationFiles = $this->getAllMigrationFiles($this->connection);
        }

        if (count($migrationFiles) === 0) {
            $this->io->warning('No migration files found');
            return;
        }

        if ($this->connection !== null) {
            $migrationFiles = $this->filterMigrationsByConnection($migrationFiles, $this->connection);

            if (count($migrationFiles) === 0) {
                $this->io->warning("No migrations found for connection: {$this->connection}");
                return;
            }
        }

        $ranMigrationsByConnection = $this->getRanMigrationsForAllConnections($migrationFiles);

        $rows = $this->buildStatusRowsWithMultipleConnections($migrationFiles, $ranMigrationsByConnection, $pendingOnly, $ranOnly);

        if (count($rows) === 0) {
            if ($pendingOnly) {
                $this->io->success('No pending migrations');
            } elseif ($ranOnly) {
                $this->io->warning('No completed migrations');
            }
            return;
        }

        $hasNestedMigrations = $this->hasNestedStructure($migrationFiles);

        if ($hasNestedMigrations) {
            $this->displayGroupedStatus($rows);
        } else {
            $this->io->table(['Migration', 'Status', 'Batch', 'Connection'], $rows);
        }

        $this->displaySummary($rows);
    }

    /**
     * Filter migration files by connection.
     *
     * @param list<string> $migrationFiles
     * @return list<string>
     */
    private function filterMigrationsByConnection(array $migrationFiles, string $connection): array
    {
        $filtered = [];

        foreach ($migrationFiles as $file) {
            $migrationConnection = $this->getMigrationConnection($file);

            if ($migrationConnection === $connection) {
                $filtered[] = $file;
            } elseif ($migrationConnection === null && $connection === 'default') {
                $filtered[] = $file;
            }
        }

        return $filtered;
    }

    /**
     * Get ran migrations for all connections used by the migration files.
     *
     * @param list<string> $migrationFiles
     * @return array<string, array<string, int>>
     */
    private function getRanMigrationsForAllConnections(array $migrationFiles): array
    {
        $ranByConnection = [];
        $connectionsToCheck = [];

        foreach ($migrationFiles as $file) {
            $migrationConnection = $this->getMigrationConnection($file);
            $connectionKey = $migrationConnection ?? 'default';
            $connectionsToCheck[$connectionKey] = $migrationConnection;
        }

        foreach ($connectionsToCheck as $connectionKey => $connectionName) {
            $repository = new MigrationRepository(
                $this->getMigrationsTable($connectionName),
                $connectionName
            );

            try {
                await($repository->createRepository());
                /** @var list<array<string, mixed>> $ranMigrations */
                $ranMigrations = await($repository->getRan());

                $ranMap = [];
                foreach ($ranMigrations as $migration) {
                    $path = $migration['migration'] ?? null;
                    $batch = $migration['batch'] ?? null;
                    if (is_string($path)) {
                        $normalizedPath = str_replace('\\', '/', trim($path, '/\\'));
                        $ranMap[$normalizedPath] = is_int($batch) ? $batch : 0;
                    }
                }

                $ranByConnection[$connectionKey] = $ranMap;
            } catch (\Throwable $e) {
                $ranByConnection[$connectionKey] = [];
            }
        }

        return $ranByConnection;
    }

    /**
     * Build status rows checking each migration against its own connection.
     *
     * @param list<string> $migrationFiles
     * @param array<string, array<string, int>> $ranMigrationsByConnection
     * @return list<array{0: string, 1: string, 2: string, 3: string}>
     */
    private function buildStatusRowsWithMultipleConnections(
        array $migrationFiles,
        array $ranMigrationsByConnection,
        bool $pendingOnly,
        bool $ranOnly
    ): array {
        $rows = [];

        foreach ($migrationFiles as $file) {
            $relativePath = $this->getRelativeMigrationPath($file, $this->connection);
            $normalizedRelativePath = str_replace('\\', '/', trim($relativePath, '/\\'));

            $migrationConnection = $this->getMigrationConnection($file);
            $connectionKey = $migrationConnection ?? 'default';
            $connectionDisplay = $migrationConnection ?? '<comment>default</comment>';

            $ranMap = $ranMigrationsByConnection[$connectionKey] ?? [];
            $isRan = array_key_exists($normalizedRelativePath, $ranMap);

            if ($pendingOnly && $isRan) {
                continue;
            }
            if ($ranOnly && !$isRan) {
                continue;
            }

            if ($isRan) {
                $batch = $ranMap[$normalizedRelativePath];
                $batchStr = $batch > 0 ? (string) $batch : 'N/A';
                $rows[] = [$relativePath, '<info>✓ Ran</info>', $batchStr, $connectionDisplay];
            } else {
                $rows[] = [$relativePath, '<comment>Pending</comment>', '-', $connectionDisplay];
            }
        }

        return $rows;
    }

    /**
     * Check if migrations use nested directory structure.
     *
     * @param list<string> $migrationFiles
     */
    private function hasNestedStructure(array $migrationFiles): bool
    {
        foreach ($migrationFiles as $file) {
            $relativePath = $this->getRelativeMigrationPath($file, $this->connection);
            if (str_contains($relativePath, '/')) {
                return true;
            }
        }
        return false;
    }

    /**
     * Display migrations grouped by directory.
     *
     * @param list<array{0: string, 1: string, 2: string, 3: string}> $rows
     */
    private function displayGroupedStatus(array $rows): void
    {
        $grouped = [];

        foreach ($rows as $row) {
            $path = $row[0];
            $directory = dirname($path);

            if ($directory === '.') {
                $directory = '(root)';
            }

            if (!isset($grouped[$directory])) {
                $grouped[$directory] = [];
            }

            $grouped[$directory][] = [
                basename($path),
                $row[1],
                $row[2],
                $row[3]
            ];
        }

        ksort($grouped);

        foreach ($grouped as $directory => $migrations) {
            $this->io->section($directory);
            $this->io->table(['Migration', 'Status', 'Batch', 'Connection'], $migrations);
        }
    }

    /**
     * @param list<string> $migrationFiles
     * @param list<array<string, mixed>> $ranMigrations
     * @return list<array{0: string, 1: string, 2: string, 3: string}>
     */
    private function buildStatusRows(array $migrationFiles, array $ranMigrations, bool $pendingOnly, bool $ranOnly): array
    {
        $ranMigrationMap = [];
        foreach ($ranMigrations as $migration) {
            $path = $migration['migration'] ?? null;
            $batch = $migration['batch'] ?? null;
            if (is_string($path)) {
                $normalizedPath = str_replace('\\', '/', trim($path, '/\\'));
                $ranMigrationMap[$normalizedPath] = $batch;
            }
        }

        $rows = [];

        foreach ($migrationFiles as $file) {
            $relativePath = $this->getRelativeMigrationPath($file, $this->connection);

            $normalizedRelativePath = str_replace('\\', '/', trim($relativePath, '/\\'));

            $isRan = array_key_exists($normalizedRelativePath, $ranMigrationMap);

            if ($pendingOnly && $isRan) {
                continue;
            }
            if ($ranOnly && !$isRan) {
                continue;
            }

            $migrationConnection = $this->getMigrationConnection($file);
            $connectionDisplay = $migrationConnection ?? '<comment>default</comment>';

            if ($isRan) {
                $batch = $ranMigrationMap[$normalizedRelativePath];
                $batchStr = is_int($batch) ? (string) $batch : 'N/A';
                $rows[] = [$relativePath, '<info>✓ Ran</info>', $batchStr, $connectionDisplay];
            } else {
                $rows[] = [$relativePath, '<comment>Pending</comment>', '-', $connectionDisplay];
            }
        }

        return $rows;
    }

    /**
     * Get the connection name from a migration file.
     */
    private function getMigrationConnection(string $file): ?string
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

            return null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Display summary statistics.
     *
     * @param list<array{0: string, 1: string, 2: string, 3: string}> $rows
     */
    private function displaySummary(array $rows): void
    {
        $total = count($rows);
        $ran = 0;
        $pending = 0;
        $connectionCounts = [];

        foreach ($rows as $row) {
            if (str_contains($row[1], 'Ran')) {
                $ran++;
            } else {
                $pending++;
            }

            $connection = strip_tags($row[3]);
            if (!isset($connectionCounts[$connection])) {
                $connectionCounts[$connection] = 0;
            }
            $connectionCounts[$connection]++;
        }

        $this->io->newLine();
        $this->io->writeln([
            "Total migrations: <info>{$total}</info>",
            "Completed: <info>{$ran}</info>",
            "Pending: <comment>{$pending}</comment>",
        ]);

        if (count($connectionCounts) > 1) {
            $this->io->newLine();
            $this->io->writeln('<comment>By connection:</comment>');
            foreach ($connectionCounts as $conn => $count) {
                $this->io->writeln("  {$conn}: <info>{$count}</info>");
            }
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

    private function handleError(\Throwable $e): void
    {
        $this->io->error('Failed to get migration status: ' . $e->getMessage());
        if ($this->io->isVerbose()) {
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
