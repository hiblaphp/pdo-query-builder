<?php

namespace Hibla\PdoQueryBuilder\Console;

use Hibla\PdoQueryBuilder\DB;
use Hibla\PdoQueryBuilder\Schema\MigrationRepository;
use Hibla\PdoQueryBuilder\Schema\SchemaBuilder;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class MigrateResetCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('migrate:reset')
            ->setDescription('Rollback all database migrations');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Reset All Migrations');

        if (!$io->confirm('This will rollback ALL migrations. Are you sure?', false)) {
            $io->warning('Reset cancelled');
            return Command::SUCCESS;
        }

        $projectRoot = $this->findProjectRoot();
        if (!$projectRoot) {
            $io->error('Could not find project root');
            return Command::FAILURE;
        }

        $migrationsPath = $projectRoot . '/database/migrations';

        try {
            // Initialize DB system
            $this->initializeDatabase($io);

            $repository = new MigrationRepository('migrations');
            $schema = new SchemaBuilder();

            $allMigrations = await($repository->getRan());

            if (empty($allMigrations)) {
                $io->warning('Nothing to reset');
                return Command::SUCCESS;
            }

            $io->section('Resetting all migrations');

            // Rollback in reverse order
            $allMigrations = array_reverse($allMigrations);

            foreach ($allMigrations as $migrationData) {
                $migrationName = $migrationData['migration'];
                $file = $migrationsPath . '/' . $migrationName;

                if (!file_exists($file)) {
                    $io->warning("Migration file not found: {$migrationName}");
                    await($repository->delete($migrationName));
                    continue;
                }

                try {
                    $migration = require $file;
                    
                    if (method_exists($migration, 'down')) {
                        $io->write("Rolling back: {$migrationName}...");
                        await($migration->down($schema));
                        $io->writeln(' <info>✓</info>');
                    }
                    
                    await($repository->delete($migrationName));
                } catch (\Throwable $e) {
                    $io->newLine();
                    $io->error("Failed to rollback {$migrationName}: " . $e->getMessage());
                    if ($output->isVerbose()) {
                        $io->writeln($e->getTraceAsString());
                    }
                }
            }

            $io->success('All migrations have been reset!');

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error('Reset failed: ' . $e->getMessage());
            if ($output->isVerbose()) {
                $io->writeln($e->getTraceAsString());
            }
            return Command::FAILURE;
        }
    }

    private function initializeDatabase(SymfonyStyle $io): void
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