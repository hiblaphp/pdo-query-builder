<?php

declare(strict_types=1);

namespace Hibla\PdoQueryBuilder\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class MakeMigrationCommand extends Command
{
    private SymfonyStyle $io;
    private string $projectRoot;
    private string $migrationsPath;
    private string $migrationName;
    private ?string $table;
    private ?string $alter;

    protected function configure(): void
    {
        $this
            ->setName('make:migration')
            ->setDescription('Create a new migration file')
            ->addArgument('name', InputArgument::REQUIRED, 'Migration name')
            ->addOption('table', null, InputOption::VALUE_OPTIONAL, 'Table to create')
            ->addOption('alter', null, InputOption::VALUE_OPTIONAL, 'Table to alter');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->io->title('Create Migration');

        $this->migrationName = $input->getArgument('name');
        $this->table = $input->getOption('table');
        $this->alter = $input->getOption('alter');

        if (!$this->initializeProjectRoot()) {
            return Command::FAILURE;
        }

        if (!$this->ensureMigrationsDirectory()) {
            return Command::FAILURE;
        }

        if (!$this->createMigrationFile()) {
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function initializeProjectRoot(): bool
    {
        $this->projectRoot = $this->findProjectRoot();
        if (!$this->projectRoot) {
            $this->io->error('Could not find project root');
            return false;
        }
        return true;
    }

    private function ensureMigrationsDirectory(): bool
    {
        $this->migrationsPath = $this->projectRoot . '/database/migrations';
        if (!is_dir($this->migrationsPath) && !mkdir($this->migrationsPath, 0755, true)) {
            $this->io->error("Failed to create migrations directory");
            return false;
        }
        return true;
    }

    private function createMigrationFile(): bool
    {
        $fileName = $this->generateFileName();
        $filePath = $this->migrationsPath . '/' . $fileName;
        $stub = $this->generateMigrationStub();

        if (file_put_contents($filePath, $stub) === false) {
            $this->io->error("Failed to create migration file");
            return false;
        }

        $this->io->success("Migration created: database/migrations/{$fileName}");
        return true;
    }

    private function generateFileName(): string
    {
        $timestamp = date('Y_m_d_His');
        return "{$timestamp}_{$this->migrationName}.php";
    }

    private function generateMigrationStub(): string
    {
        if ($this->table) {
            return $this->getCreateStub();
        }

        if ($this->alter) {
            return $this->getAlterStub();
        }

        return $this->getBlankStub();
    }

    private function getCreateStub(): string
    {
        return "<?php

use Hibla\PdoQueryBuilder\Schema\Blueprint;
use Hibla\PdoQueryBuilder\Schema\SchemaBuilder;
use Hibla\Promise\Interfaces\PromiseInterface;

return new class
{
    public function up(SchemaBuilder \$schema): PromiseInterface
    {
        return \$schema->create('{$this->table}', function (Blueprint \$table) {
            \$table->id();
            \$table->timestamps();
        });
    }

    public function down(SchemaBuilder \$schema): PromiseInterface
    {
        return \$schema->dropIfExists('{$this->table}');
    }
};
";
    }

    private function getAlterStub(): string
    {
        return "<?php

use Hibla\PdoQueryBuilder\Schema\Blueprint;
use Hibla\PdoQueryBuilder\Schema\SchemaBuilder;
use Hibla\Promise\Interfaces\PromiseInterface;

return new class
{
    public function up(SchemaBuilder \$schema): PromiseInterface
    {
        return \$schema->table('{$this->alter}', function (Blueprint \$table) {
            // Add columns, indexes, etc.
        });
    }

    public function down(SchemaBuilder \$schema): PromiseInterface
    {
        return \$schema->table('{$this->alter}', function (Blueprint \$table) {
            // Reverse the changes
        });
    }
};
";
    }

    private function getBlankStub(): string
    {
        return "<?php

use Hibla\PdoQueryBuilder\Schema\Blueprint;
use Hibla\PdoQueryBuilder\Schema\SchemaBuilder;
use Hibla\Promise\Interfaces\PromiseInterface;
use Hibla\Promise\Promise;

return new class
{
    public function up(SchemaBuilder \$schema): PromiseInterface
    {
        // Write your migration here and return a promise
    }

    public function down(SchemaBuilder \$schema): PromiseInterface
    {
        // Reverse your migration here and return a promise
    }
};
";
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