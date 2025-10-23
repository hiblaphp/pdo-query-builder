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

        $resetResult = $this->resetMigrations($step, $path);

        if ($resetResult === false) {
            $this->io->error('Reset failed');
            return Command::FAILURE;
        }

        if ($resetResult === 0) {
            $this->io->warning('No migrations to reset');
            return Command::SUCCESS;
        }

        $migrateResult = $this->runMigrations($path);

        if ($migrateResult === false) {
            $this->io->error('Migration failed');
            return Command::FAILURE;
        }

        if ($migrateResult === 0) {
            $this->io->warning('No migrations to run');
            return Command::SUCCESS;
        }

        if ($input->getOption('seed')) {
            $this->io->newLine();
            $this->io->section('Running seeders');

            if ($this->runSeeders()) {
                $this->io->writeln('<info>✓ Seeders completed</info>');
            } else {
                $this->io->warning('Seeders not available or failed');
            }
        }

        $this->io->newLine();
        $this->io->success('Database refreshed successfully!');

        return Command::SUCCESS;
    }

    /**
     * @return int|false Number of migrations reset, or false on failure
     */
    private function resetMigrations(?int $step, ?string $path): int|false
    {
        $commandName = $step !== null ? 'migrate:rollback' : 'migrate:reset';

        $autoConfirm = $this->input->getOption('force') === true;

        if ($autoConfirm) {
            $this->io->section('Resetting migrations');

            $bufferedOutput = new BufferedOutput();
            $result = $this->executeCommand($commandName, $step, $path, $bufferedOutput, true);

            $content = $bufferedOutput->fetch();

            $lines = explode("\n", $content);
            $rollbackCount = 0;

            foreach ($lines as $line) {
                $trimmedLine = trim($line);

                if (str_contains($line, 'Rolling back:')) {
                    $this->output->writeln($line);
                    $rollbackCount++;
                }

                if (str_contains($trimmedLine, 'Nothing to reset')) {
                    return 0;
                }
            }

            return $result ? $rollbackCount : false;
        } else {
            $result = $this->executeCommand($commandName, $step, $path, $this->output, false);

            if (!$result) {
                return false;
            }

            return 1;
        }
    }

    /**
     * @return int|false Number of migrations run, or false on failure
     */
    private function runMigrations(?string $path): int|false
    {
        $bufferedOutput = new BufferedOutput();
        $result = $this->executeCommand('migrate', null, $path, $bufferedOutput);

        $content = $bufferedOutput->fetch();

        $lines = explode("\n", $content);
        $inMigrationSection = false;
        $migrationCount = 0;

        foreach ($lines as $line) {
            $trimmedLine = trim($line);

            if (str_contains($trimmedLine, 'Running migrations')) {
                $this->io->newLine();
                $this->io->section('Running migrations');
                $inMigrationSection = true;
                continue;
            }

            if ($inMigrationSection && str_contains($line, 'Migrating:')) {
                $this->output->writeln($line);
                $migrationCount++;
            }

            if (str_contains($trimmedLine, 'Nothing to migrate')) {
                return 0;
            }
        }

        return $result ? $migrationCount : false;
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

            $input = new ArrayInput($arguments);
            $code = $command->run($input, $this->output);

            return $code === Command::SUCCESS;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function executeCommand(
        string $commandName,
        ?int $step = null,
        ?string $path = null,
        ?OutputInterface $customOutput = null,
        bool $autoConfirm = false
    ): bool {
        $application = $this->getApplication();
        if ($application === null) {
            $this->io->error('Application instance not found.');
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

            $input = new ArrayInput($arguments);

            if ($autoConfirm) {
                $input->setInteractive(true);
                $input->setStream($this->createYesInputStream());
            }

            $outputToUse = $customOutput ?? $this->output;
            $code = $command->run($input, $outputToUse);

            return $code === Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->io->error("Failed to execute command '{$commandName}': " . $e->getMessage());
            return false;
        }
    }

    /** 
     * @return resource
     */
    private function createYesInputStream()
    {
        $stream = fopen('php://memory', 'r+');
        if ($stream === false) {
            throw new \RuntimeException('Failed to create input stream');
        }

        fwrite($stream, "yes\n");
        rewind($stream);
        return $stream;
    }
}
