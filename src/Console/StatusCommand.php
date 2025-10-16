<?php

namespace Hibla\PdoQueryBuilder\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class StatusCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('status')
            ->setDescription('Check PDO Query Builder configuration status');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('PDO Query Builder - Status');

        $projectRoot = $this->findProjectRoot();
        if (!$projectRoot) {
            $io->error('Could not find project root');
            return Command::FAILURE;
        }

        $configFile = $projectRoot . '/config/pdo-query-builder.php';
        $envFile = $projectRoot . '/.env';

        $io->table(['Item', 'Status'], [
            ['Project Root', $projectRoot],
            ['Config File', file_exists($configFile) ? '✓ Found' : '✗ Missing'],
            ['.env File', file_exists($envFile) ? '✓ Found' : '✗ Missing'],
        ]);

        if (!file_exists($configFile)) {
            $io->note('Run: ./vendor/bin/pdo-query-builder init');
            return Command::FAILURE;
        }

        $io->success('All configured!');
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
}