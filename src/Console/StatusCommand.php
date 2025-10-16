<?php

declare(strict_types=1);

namespace Hibla\PdoQueryBuilder\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class StatusCommand extends Command
{
    private SymfonyStyle $io;
    private string $projectRoot;
    private array $configFiles = [
        'pdo-query-builder.php' => 'Config File',
        'pdo-schema.php' => 'Schema File',
    ];

    protected function configure(): void
    {
        $this
            ->setName('status')
            ->setDescription('Check PDO Query Builder configuration status');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->io->title('PDO Query Builder - Status');

        $this->projectRoot = $this->findProjectRoot();
        if (!$this->projectRoot) {
            $this->io->error('Could not find project root');
            return Command::FAILURE;
        }

        $this->displayStatusTable();

        if (!$this->allRequiredFilesExist()) {
            $this->io->note('Run: ./vendor/bin/pdo-query-builder init');
            return Command::FAILURE;
        }

        $this->io->success('All configured!');
        return Command::SUCCESS;
    }

    private function displayStatusTable(): void
    {
        $rows = $this->buildStatusRows();
        $this->io->table(['Item', 'Status'], $rows);
    }

    private function buildStatusRows(): array
    {
        $rows = [
            ['Project Root', $this->projectRoot],
        ];

        foreach ($this->configFiles as $filename => $label) {
            $filePath = $this->getConfigFilePath($filename);
            $status = file_exists($filePath) ? '✓ Found' : '✗ Missing';
            $rows[] = [$label, $status];
        }

        $envFile = $this->projectRoot . '/.env';
        $envStatus = file_exists($envFile) ? '✓ Found' : '✗ Missing';
        $rows[] = ['.env File', $envStatus];

        return $rows;
    }

    private function allRequiredFilesExist(): bool
    {
        foreach ($this->configFiles as $filename => $label) {
            $filePath = $this->getConfigFilePath($filename);
            if (!file_exists($filePath)) {
                return false;
            }
        }
        return true;
    }

    private function getConfigFilePath(string $filename): string
    {
        return $this->projectRoot . '/config/' . $filename;
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
