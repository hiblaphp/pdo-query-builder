<?php

namespace Hibla\PdoQueryBuilder\Console;

use Hibla\PdoQueryBuilder\DB;
use Hibla\PdoQueryBuilder\Schema\MigrationRepository;
use Hibla\PdoQueryBuilder\Schema\SchemaBuilder;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class MigrateCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('migrate')
            ->setDescription('Run pending database migrations')
            ->addOption('step', null, InputOption::VALUE_OPTIONAL, 'Number of migrations to run', 0)
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force the operation to run');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Database Migrations');

        $projectRoot = $this->findProjectRoot();
        if (!$projectRoot) {
            $io->error('Could not find project root');
            return Command::FAILURE;
        }

        $migrationsPath = $projectRoot . '/database/migrations';
        if (!is_dir($migrationsPath)) {
            $io->error('Migrations directory not found. Run make:migration first.');
            return Command::FAILURE;
        }

        try {
            $this->initializeDatabase($io);

            $repository = new MigrationRepository('migrations');
            $schema = new SchemaBuilder();

            // Create migrations table if it doesn't exist
            $io->writeln('Preparing migration repository...');
            await($repository->createRepository());

            // Get already ran migrations
            $ranMigrations = await($repository->getRan());
            $ranMigrationNames = array_column($ranMigrations, 'migration');

            // Get all migration files
            $files = glob($migrationsPath . '/*.php');
            if ($files === false || empty($files)) {
                $io->warning('No migration files found');
                return Command::SUCCESS;
            }

            sort($files);

            // Filter pending migrations
            $pendingMigrations = array_filter($files, function ($file) use ($ranMigrationNames) {
                return !in_array(basename($file), $ranMigrationNames);
            });

            if (empty($pendingMigrations)) {
                $io->success('Nothing to migrate');
                return Command::SUCCESS;
            }

            // Get next batch number
            $batchNumber = await($repository->getNextBatchNumber());
            $batchNumber = ($batchNumber ?? 0) + 1;

            $step = (int) $input->getOption('step');
            if ($step > 0) {
                $pendingMigrations = array_slice($pendingMigrations, 0, $step);
            }

            $io->section('Running migrations');

            foreach ($pendingMigrations as $file) {
                $migrationName = basename($file);
                
                try {
                    $migration = require $file;
                    
                    if (!method_exists($migration, 'up')) {
                        $io->error("Migration {$migrationName} does not have an up() method");
                        continue;
                    }

                    $io->write("Migrating: {$migrationName}...");
                    
                    await($migration->up($schema));
                    await($repository->log($migrationName, $batchNumber));
                    
                    $io->writeln(' <info>✓</info>');
                } catch (\Throwable $e) {
                    $io->newLine();
                    $io->error("Failed to run migration {$migrationName}: " . $e->getMessage());
                    if ($output->isVerbose()) {
                        $io->writeln($e->getTraceAsString());
                    }
                    return Command::FAILURE;
                }
            }

            $io->success('Migrations completed successfully!');

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error('Migration failed: ' . $e->getMessage());
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