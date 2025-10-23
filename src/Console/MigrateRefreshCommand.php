<?php

declare(strict_types=1);

namespace Hibla\PdoQueryBuilder\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class MigrateRefreshCommand extends Command
{
    private SymfonyStyle $io;
    private OutputInterface $output;
    private InputInterface $input;

    protected function configure(): void
    {
        $this
            ->setName('migrate:refresh')
            ->setDescription('Reset and re-run all migrations')
            ->addOption('connection', null, InputOption::VALUE_OPTIONAL, 'The database connection to use')
            ->addOption('path', null, InputOption::VALUE_OPTIONAL, 'The path to migrations files')
            ->addOption('step', null, InputOption::VALUE_OPTIONAL, 'Number of migrations to rollback')
            ->addOption('seed', null, InputOption::VALUE_NONE, 'Run seeders after migrations')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Force the operation without confirmation')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->output = $output;
        $this->input = $input;
        $this->io->title('Refresh Migrations');

        $connectionOption = $input->getOption('connection');
        $connection = (is_string($connectionOption) && $connectionOption !== '') ? $connectionOption : null;

        if ($connection !== null) {
            $this->io->note("Using database connection: {$connection}");
        }

        $pathOption = $input->getOption('path');
        $path = is_string($pathOption) && $pathOption !== '' ? $pathOption : null;

        if ($path !== null) {
            $this->io->note("Using migration path: {$path}");
        }

        $stepOption = $input->getOption('step');
        $step = is_numeric($stepOption) ? (int) $stepOption : null;

        if (!$input->getOption('force')) {
            $message = $step !== null 
                ? "This will rollback the last {$step} migration(s) and re-run them. Continue?" 
                : 'This will rollback ALL migrations and re-run them. Continue?';
            
            if (!$this->io->confirm($message, false)) {
                $this->io->info('Operation cancelled');
                return Command::SUCCESS;
            }
        }

        $this->io->newLine();
        $this->io->section($step !== null ? 'Rolling back migrations' : 'Resetting database');
        
        $resetResult = $this->resetMigrations($step, $path);

        if ($resetResult === false) {
            $this->io->error('Reset failed');
            return Command::FAILURE;
        }

        if ($resetResult === 0) {
            $this->io->info('Nothing to reset');
        }

        $this->io->newLine();
        $this->io->section('Running migrations');
        
        $migrateResult = $this->runMigrations($path);

        if ($migrateResult === false) {
            $this->io->error('Migration failed');
            return Command::FAILURE;
        }

        if ($migrateResult === 0) {
            $this->io->info('Nothing to migrate');
        }

        if ($input->getOption('seed')) {
            $this->io->newLine();
            $this->io->section('Running seeders');

            if ($this->runSeeders()) {
                $this->io->writeln('<info>✓ Seeders completed successfully</info>');
            } else {
                $this->io->warning('Seeders not available or failed');
            }
        }

        $this->io->newLine();
        $this->io->success('Database refreshed successfully!');

        return Command::SUCCESS;
    }

    /**
     * Reset migrations
     * 
     * @return int|false Number of migrations reset (0 if nothing to reset), false on error
     */
    private function resetMigrations(?int $step, ?string $path): int|false
    {
        $commandName = $step !== null ? 'migrate:rollback' : 'migrate:reset';
        
        $bufferedOutput = new BufferedOutput();
       
        $result = $this->executeCommand($commandName, $step, $path, $bufferedOutput, true);
        
        if (!$result) {
            return false;
        }
        
        $content = $bufferedOutput->fetch();
        
        if (str_contains($content, 'Nothing to reset') || str_contains($content, 'Nothing to rollback')) {
            return 0;
        }
        
        $count = $this->displayFilteredOutput($content, [
            'Rolling back:',
            '✓',
            'Rolled back:',
        ]);

        return $count > 0 ? $count : 1;
    }

    /**
     * Run migrations
     * 
     * @return int|false Number of migrations run (0 if nothing to migrate), false on failure
     */
    private function runMigrations(?string $path): int|false
    {
        $bufferedOutput = new BufferedOutput();
        
        $result = $this->executeCommand('migrate', null, $path, $bufferedOutput, false);

        if (!$result) {
            return false;
        }

        $content = $bufferedOutput->fetch();
        
        if (str_contains($content, 'Nothing to migrate')) {
            return 0;
        }
        
        $count = $this->displayFilteredOutput($content, [
            'Migrating:',
            '✓',
            'Migrated:',
        ]);

        return $count > 0 ? $count : 1;
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

            $connectionOption = $this->input->getOption('connection');
            if (is_string($connectionOption) && $connectionOption !== '') {
                $arguments['--connection'] = $connectionOption;
            }

            $bufferedOutput = new BufferedOutput();
            $input = new ArrayInput($arguments);
            $code = $command->run($input, $bufferedOutput);

            if ($code === Command::SUCCESS) {
                $content = $bufferedOutput->fetch();
                $this->displayFilteredOutput($content, [
                    'Seeding:',
                    '✓',
                    'Seeded:',
                ]);
            }

            return $code === Command::SUCCESS;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Display filtered output based on keywords
     * 
     * @return int Number of lines displayed
     */
    private function displayFilteredOutput(string $content, array $keywords): int
    {
        $lines = explode("\n", $content);
        $displayedCount = 0;
        
        foreach ($lines as $line) {
            $trimmedLine = trim($line);
            
            if ($trimmedLine === '') {
                continue;
            }
            
            if (preg_match('/^[=\-]+$/', $trimmedLine)) {
                continue;
            }
            
            if (preg_match('/^(Reset Migrations|Database Migrations|Running migrations|Preparing migration)/', $trimmedLine)) {
                continue;
            }
            
            $shouldDisplay = false;
            foreach ($keywords as $keyword) {
                if (str_contains($line, $keyword)) {
                    $shouldDisplay = true;
                    break;
                }
            }
            
            if ($shouldDisplay) {
                $this->output->writeln($line);
                $displayedCount++;
            }
        }
        
        return $displayedCount;
    }

    private function executeCommand(
        string $commandName,
        ?int $step = null,
        ?string $path = null,
        ?OutputInterface $customOutput = null,
        bool $forceFlag = false
    ): bool {
        $application = $this->getApplication();
        if ($application === null) {
            $this->io->error('Application instance not found');
            return false;
        }

        try {
            $command = $application->find($commandName);
            $arguments = [];

            $connectionOption = $this->input->getOption('connection');
            if (is_string($connectionOption) && $connectionOption !== '') {
                $arguments['--connection'] = $connectionOption;
            }

            if ($path !== null) {
                $arguments['--path'] = $path;
            }

            if ($step !== null && $commandName === 'migrate:rollback') {
                $arguments['--step'] = $step;
            }

            if ($forceFlag) {
                $arguments['--force'] = true;
            }

            $input = new ArrayInput($arguments);
            $outputToUse = $customOutput ?? $this->output;
            $code = $command->run($input, $outputToUse);

            return $code === Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->io->error("Failed to execute command '{$commandName}': " . $e->getMessage());
            return false;
        }
    }
}