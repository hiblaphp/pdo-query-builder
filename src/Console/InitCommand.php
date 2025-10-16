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
            ->setHelp('Copies the default configuration file to your project\'s config directory.')
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

        $sourceConfig = $this->getSourceConfigPath();
        $destConfig = $configDir . '/pdo-query-builder.php';

        if (file_exists($destConfig) && !$input->getOption('force')) {
            if (!$io->confirm('Configuration already exists. Overwrite?', false)) {
                $io->warning('Cancelled');
                return Command::SUCCESS;
            }
        }

        if (!file_exists($sourceConfig)) {
            $io->error("Source config not found: {$sourceConfig}");
            return Command::FAILURE;
        }

        if (!copy($sourceConfig, $destConfig)) {
            $io->error("Failed to copy configuration");
            return Command::FAILURE;
        }

        $io->success('✓ Configuration created: config/pdo-query-builder.php');

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

    private function getSourceConfigPath(): string
    {
        $paths = [
            __DIR__ . '/../../config/pdo-query-builder.php',
            __DIR__ . '/../../../config/pdo-query-builder.php',
        ];
        
        foreach ($paths as $path) {
            if (file_exists($path)) return $path;
        }
        
        return __DIR__ . '/../../config/pdo-query-builder.php';
    }
}