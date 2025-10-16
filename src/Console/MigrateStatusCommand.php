<?php

namespace Hibla\PdoQueryBuilder\Console;

use Hibla\PdoQueryBuilder\DB;
use Hibla\PdoQueryBuilder\Schema\MigrationRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class MigrateStatusCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('migrate:status')
            ->setDescription('Show the status of each migration');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Migration Status');

        $projectRoot = $this->findProjectRoot();
        if (!$projectRoot) {
            $io->error('Could not find project root');
            return Command::FAILURE;
        }

        $migrationsPath = $projectRoot . '/database/migrations';
        if (!is_dir($migrationsPath)) {
            $io->warning('Migrations directory not found');
            return Command::SUCCESS;
        }

        try { 
            $this->initializeDatabase($io);

            $repository = new MigrationRepository('migrations');
       
            await($repository->createRepository());
            
            $files = glob($migrationsPath . '/*.php');
            if ($files === false || empty($files)) {
                $io->warning('No migration files found');
                return Command::SUCCESS;
            }

            sort($files);

            $ranMigrations = await($repository->getRan());
            $ranMigrationNames = array_column($ranMigrations, 'migration');

            $rows = [];
            foreach ($files as $file) {
                $migrationName = basename($file);
                $status = in_array($migrationName, $ranMigrationNames) ? '<info>✓ Ran</info>' : '<comment>Pending</comment>';
                
                $rows[] = [$migrationName, $status];
            }

            $io->table(['Migration', 'Status'], $rows);

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error('Failed to get migration status: ' . $e->getMessage());
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