<?php

declare(strict_types=1);

namespace Hibla\PdoQueryBuilder\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class InitCommand extends Command
{
    private SymfonyStyle $io;
    private string $projectRoot;
    private bool $force;

    protected function configure(): void
    {
        $this
            ->setName('init')
            ->setDescription('Initialize PDO Query Builder configuration')
            ->setHelp('Copies the default configuration files to your project\'s config directory.')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Overwrite existing configuration');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->force = $input->getOption('force');

        $this->io->title('PDO Query Builder - Initialize');

        $this->projectRoot = $this->findProjectRoot();
        if (!$this->projectRoot) {
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

        $this->promptEnvFileCreation();

        return Command::SUCCESS;
    }

    private function ensureConfigDirectoryExists(): ?string
    {
        $configDir = $this->projectRoot . '/config';
        if (!is_dir($configDir) && !mkdir($configDir, 0755, true)) {
            $this->io->error("Failed to create config directory");
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

        return empty($failedFiles) ? Command::SUCCESS : Command::FAILURE;
    }

    private function copyFile(string $filename, string $sourceConfig, string $configDir): bool
    {
        $destConfig = $configDir . '/' . $filename;

        if (file_exists($destConfig) && !$this->force) {
            if (!$this->io->confirm("File '{$filename}' already exists. Overwrite?", false)) {
                $this->io->warning("Skipped: {$filename}");
                return true;
            }
        }

        if (!file_exists($sourceConfig)) {
            $this->io->error("Source config not found: {$sourceConfig}");
            return false;
        }

        if (!copy($sourceConfig, $destConfig)) {
            $this->io->error("Failed to copy {$filename}");
            return false;
        }

        return true;
    }

    private function promptEnvFileCreation(): void
    {
        if (!file_exists($this->projectRoot . '/.env')) {
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

    private function getSourceConfigPath(string $filename): string
    {
        $paths = [
            __DIR__ . "/../../config/{$filename}",
            __DIR__ . "/../../../config/{$filename}",
        ];
        
        foreach ($paths as $path) {
            if (file_exists($path)) return $path;
        }
        
        return __DIR__ . "/../../config/{$filename}";
    }
}