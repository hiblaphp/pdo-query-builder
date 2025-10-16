<?php

namespace Hibla\PdoQueryBuilder\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class MigrateRefreshCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('migrate:refresh')
            ->setDescription('Reset and re-run all migrations');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Refresh Migrations');

        $resetCommand = $this->getApplication()->find('migrate:reset');
        $resetInput = new ArrayInput([]);
        $resetCode = $resetCommand->run($resetInput, $output);

        if ($resetCode !== Command::SUCCESS) {
            $io->error('Reset failed');
            return Command::FAILURE;
        }

        $migrateCommand = $this->getApplication()->find('migrate');
        $migrateInput = new ArrayInput([]);
        $migrateCode = $migrateCommand->run($migrateInput, $output);

        if ($migrateCode !== Command::SUCCESS) {
            $io->error('Migration failed');
            return Command::FAILURE;
        }

        $io->success('Database refreshed successfully!');

        return Command::SUCCESS;
    }
}