<?php

declare(strict_types=1);

namespace Hibla\PdoQueryBuilder\Console;

use Hibla\PdoQueryBuilder\Console\Traits\FindProjectRoot;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class InitCommand extends Command
{
    use FindProjectRoot;

    private SymfonyStyle $io;
    private ?string $projectRoot = null;
    private bool $force;

    protected function configure(): void
    {
        $this
            ->setName('init')
            ->setDescription('Initialize PDO Query Builder configuration')
            ->setHelp('Copies the default configuration files to your project\'s config directory.')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Overwrite existing configuration')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->force = (bool) $input->getOption('force');

        $this->io->title('PDO Query Builder - Initialize');

        $this->projectRoot = $this->findProjectRoot();
        if ($this->projectRoot === null) {
            $this->io->error('Could not find project root');

            return Command::FAILURE;
        }

        $configDir = $this->ensureConfigDirectoryExists();
        if ($configDir === null) {
            return Command::FAILURE;
        }

        if ($this->copyConfigFiles($configDir) === Command::FAILURE) {
            return Command::FAILURE;
        }

        $this->createAsyncPdoExecutable();

        $this->promptEnvFileCreation();

        return Command::SUCCESS;
    }

    private function ensureConfigDirectoryExists(): ?string
    {
        $configDir = $this->projectRoot.'/config';
        if (! is_dir($configDir) && ! mkdir($configDir, 0755, true)) {
            $this->io->error('Failed to create config directory');

            return null;
        }

        return $configDir;
    }

    private function copyConfigFiles(string $configDir): int
    {
        $files = [
            'pdo-query-builder.php' => $this->getSourceConfigPath('pdo-query-builder.php'),
            'pdo-schema.php' => $this->getSourceConfigPath('pdo-schema.php'),
        ];

        $failedFiles = [];

        foreach ($files as $filename => $sourceConfig) {
            if ($this->copyFile($filename, $sourceConfig, $configDir)) {
                $this->io->success("✓ Configuration created: config/{$filename}");
            } else {
                $failedFiles[] = $filename;
            }
        }

        return count($failedFiles) === 0 ? Command::SUCCESS : Command::FAILURE;
    }

    private function copyFile(string $filename, string $sourceConfig, string $configDir): bool
    {
        $destConfig = $configDir.'/'.$filename;

        if (file_exists($destConfig) && ! $this->force) {
            if (! $this->io->confirm("File '{$filename}' already exists. Overwrite?", false)) {
                $this->io->warning("Skipped: {$filename}");

                return true;
            }
        }

        if (! file_exists($sourceConfig)) {
            $this->io->error("Source config not found: {$sourceConfig}");

            return false;
        }

        if (! copy($sourceConfig, $destConfig)) {
            $this->io->error("Failed to copy {$filename}");

            return false;
        }

        return true;
    }

    private function createAsyncPdoExecutable(): void
    {
        $asyncPdoPath = $this->projectRoot.'/async-pdo';

        if (file_exists($asyncPdoPath) && ! $this->force) {
            $this->io->warning('async-pdo file already exists. Use --force to overwrite.');

            return;
        }

        $stub = $this->getAsyncPdoStub();

        if (file_put_contents($asyncPdoPath, $stub) === false) {
            $this->io->error('Failed to create async-pdo file');

            return;
        }

        if (DIRECTORY_SEPARATOR === '/') {
            chmod($asyncPdoPath, 0755);
        }

        $this->io->success('✓ Created async-pdo executable');
        $this->io->section('Usage:');
        $this->io->listing([
            'php async-pdo init',
            'php async-pdo publish:templates',
            'php async-pdo migrate',
            'php async-pdo make:migration create_users_table',
            'php async-pdo migrate:rollback',
            'php async-pdo migrate:status',
        ]);
    }

    private function getAsyncPdoStub(): string
    {
        return <<<'PHP'
#!/usr/bin/env php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use Symfony\Component\Console\Application;
use Hibla\PdoQueryBuilder\Console\InitCommand;
use Hibla\PdoQueryBuilder\Console\PublishTemplatesCommand;
use Hibla\PdoQueryBuilder\Console\MakeMigrationCommand;
use Hibla\PdoQueryBuilder\Console\MigrateCommand;
use Hibla\PdoQueryBuilder\Console\MigrateRollbackCommand;
use Hibla\PdoQueryBuilder\Console\MigrateResetCommand;
use Hibla\PdoQueryBuilder\Console\MigrateRefreshCommand;
use Hibla\PdoQueryBuilder\Console\MigrateFreshCommand;
use Hibla\PdoQueryBuilder\Console\MigrateStatusCommand;
use Hibla\PdoQueryBuilder\Console\StatusCommand;

$application = new Application('Async PDO Query Builder', '1.0.0');

// Register all commands
$application->add(new InitCommand());
$application->add(new PublishTemplatesCommand());
$application->add(new MakeMigrationCommand());
$application->add(new MigrateCommand());
$application->add(new MigrateRollbackCommand());
$application->add(new MigrateResetCommand());
$application->add(new MigrateRefreshCommand());
$application->add(new MigrateFreshCommand());
$application->add(new MigrateStatusCommand());
$application->add(new StatusCommand());

$application->run();

PHP;
    }

    private function promptEnvFileCreation(): void
    {
        if ($this->projectRoot !== null && ! file_exists($this->projectRoot.'/.env')) {
            $this->io->section('Create .env file with:');
            $this->io->listing([
                'DB_CONNECTION=mysql',
                'DB_HOST=127.0.0.1',
                'DB_PORT=3306',
                'DB_DATABASE=your_database',
                'DB_USERNAME=root',
                'DB_PASSWORD=',
            ]);
        }
    }

    private function getSourceConfigPath(string $filename): string
    {
        $paths = [
            __DIR__."/../../config/{$filename}",
            __DIR__."/../../../config/{$filename}",
        ];

        foreach ($paths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return __DIR__."/../../config/{$filename}";
    }
}
