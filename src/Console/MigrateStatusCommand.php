<?php

declare(strict_types=1);

namespace Hibla\PdoQueryBuilder\Console;

use Hibla\PdoQueryBuilder\Console\Traits\LoadsSchemaConfiguration;
use Hibla\PdoQueryBuilder\DB;
use Hibla\PdoQueryBuilder\Schema\MigrationRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class MigrateStatusCommand extends Command
{
    use LoadsSchemaConfiguration;

    private SymfonyStyle $io;
    private ?string $projectRoot = null;
    private string $migrationsPath;

    protected function configure(): void
    {
        $this
            ->setName('migrate:status')
            ->setDescription('Show the status of each migration')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->io->title('Migration Status');

        if (! $this->initializeProjectRoot()) {
            return Command::FAILURE;
        }

        if (! $this->validateMigrationsDirectory()) {
            return Command::SUCCESS;
        }

        try {
            $this->initializeDatabase();
            $this->displayMigrationStatus();

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

    private function validateMigrationsDirectory(): bool
    {
        $this->migrationsPath = $this->getMigrationsPath();
        if (! is_dir($this->migrationsPath)) {
            $this->io->warning('Migrations directory not found');

            return false;
        }

        return true;
    }

    private function displayMigrationStatus(): void
    {
        $repository = new MigrationRepository($this->getMigrationsTable());
        await($repository->createRepository());

        $migrationFiles = $this->getMigrationFiles();
        if (count($migrationFiles) === 0) {
            $this->io->warning('No migration files found');

            return;
        }

        /** @var list<array<string, mixed>> $ranMigrations */
        $ranMigrations = await($repository->getRan());
        $rows = $this->buildStatusRows($migrationFiles, $ranMigrations);

        $this->io->table(['Migration', 'Status'], $rows);
    }

    /**
     * @return list<string>
     */
    private function getMigrationFiles(): array
    {
        $files = glob($this->migrationsPath . '/*.php');
        if ($files === false) {
            return [];
        }

        sort($files);

        /** @var list<string> */
        return array_map('basename', $files);
    }

    /**
     * @param list<string> $migrationFiles
     * @param list<array<string, mixed>> $ranMigrations
     * @return list<array{0: string, 1: string}>
     */
    private function buildStatusRows(array $migrationFiles, array $ranMigrations): array
    {
        /** @var list<string> $ranMigrationNames */
        $ranMigrationNames = array_column($ranMigrations, 'migration');
        $rows = [];

        foreach ($migrationFiles as $migrationName) {
            $status = $this->getMigrationStatus($migrationName, $ranMigrationNames);
            $rows[] = [$migrationName, $status];
        }

        return $rows;
    }

    /**
     * @param list<string> $ranMigrationNames
     */
    private function getMigrationStatus(string $migrationName, array $ranMigrationNames): string
    {
        return in_array($migrationName, $ranMigrationNames, true)
            ? '<info>✓ Ran</info>'
            : '<comment>Pending</comment>';
    }

    private function initializeDatabase(): void
    {
        try {
            DB::table('_test_init');
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
