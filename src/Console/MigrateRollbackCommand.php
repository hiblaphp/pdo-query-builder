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

class MigrateRollbackCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('migrate:rollback')
            ->setDescription('Rollback the last database migration')
            ->addOption('step', null, InputOption::VALUE_OPTIONAL, 'Number of migrations to rollback', 1);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Rollback Migrations');

        $projectRoot = $this->findProjectRoot();
        if (!$projectRoot) {
            $io->error('Could not find project root');
            return Command::FAILURE;
        }

        $migrationsPath = $projectRoot . '/database/migrations';
        if (!is_dir($migrationsPath)) {
            $io->error('Migrations directory not found');
            return Command::FAILURE;
        }

        try {
            $this->initializeDatabase($io);

            $repository = new MigrationRepository('migrations');
            $schema = new SchemaBuilder();

            $step = (int) $input->getOption('step');
            
            $lastBatchMigrations = await($repository->getLast());

            if (empty($lastBatchMigrations)) {
                $io->warning('Nothing to rollback');
                return Command::SUCCESS;
            }

            if ($step > 0) {
                $lastBatchMigrations = array_slice($lastBatchMigrations, 0, $step);
            }

            $io->section('Rolling back migrations');

            $lastBatchMigrations = array_reverse($lastBatchMigrations);

            foreach ($lastBatchMigrations as $migrationData) {
                $migrationName = $migrationData['migration'];
                $file = $migrationsPath . '/' . $migrationName;

                if (!file_exists($file)) {
                    $io->warning("Migration file not found: {$migrationName}");
                    continue;
                }

                try {
                    $migration = require $file;
                    
                    if (!method_exists($migration, 'down')) {
                        $io->error("Migration {$migrationName} does not have a down() method");
                        continue;
                    }

                    $io->write("Rolling back: {$migrationName}...");
                    
                    await($migration->down($schema));
                    await($repository->delete($migrationName));
                    
                    $io->writeln(' <info>✓</info>');
                } catch (\Throwable $e) {
                    $io->newLine();
                    $io->error("Failed to rollback migration {$migrationName}: " . $e->getMessage());
                    if ($output->isVerbose()) {
                        $io->writeln($e->getTraceAsString());
                    }
                    return Command::FAILURE;
                }
            }

            $io->success('Rollback completed successfully!');

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error('Rollback failed: ' . $e->getMessage());
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