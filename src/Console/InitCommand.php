<?php

namespace Hibla\PdoQueryBuilder\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class InitCommand extends Command
{
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
        $io = new SymfonyStyle($input, $output);
        $io->title('PDO Query Builder - Initialize');

        $projectRoot = $this->findProjectRoot();
        if (!$projectRoot) {
            $io->error('Could not find project root');
            return Command::FAILURE;
        }

        $configDir = $projectRoot . '/config';
        if (!is_dir($configDir) && !mkdir($configDir, 0755, true)) {
            $io->error("Failed to create config directory");
            return Command::FAILURE;
        }

        $force = $input->getOption('force');
        $files = [
            'pdo-query-builder.php' => $this->getSourceConfigPath('pdo-query-builder.php'),
            'pdo-schema.php' => $this->getSourceConfigPath('pdo-schema.php'),
        ];

        $failedFiles = [];

        foreach ($files as $filename => $sourceConfig) {
            $destConfig = $configDir . '/' . $filename;

            if (file_exists($destConfig) && !$force) {
                if (!$io->confirm("File '{$filename}' already exists. Overwrite?", false)) {
                    $io->warning("Skipped: {$filename}");
                    continue;
                }
            }

            if (!file_exists($sourceConfig)) {
                $io->error("Source config not found: {$sourceConfig}");
                $failedFiles[] = $filename;
                continue;
            }

            if (!copy($sourceConfig, $destConfig)) {
                $io->error("Failed to copy {$filename}");
                $failedFiles[] = $filename;
                continue;
            }

            $io->success("✓ Configuration created: config/{$filename}");
        }

        if (!empty($failedFiles)) {
            return Command::FAILURE;
        }

        if (!file_exists($projectRoot . '/.env')) {
            $io->section('Create .env file with:');
            $io->listing([
                'DB_CONNECTION=mysql',
                'DB_HOST=127.0.0.1',
                'DB_PORT=3306',
                'DB_DATABASE=your_database',
                'DB_USERNAME=root',
                'DB_PASSWORD=',
            ]);
        }

        return Command::SUCCESS;
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