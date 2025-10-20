<?php

declare(strict_types=1);

namespace Hibla\PdoQueryBuilder\Console;

use Carbon\Carbon;
use Hibla\PdoQueryBuilder\Console\Traits\LoadsSchemaConfiguration;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class MakeMigrationCommand extends Command
{
    use LoadsSchemaConfiguration;

    private SymfonyStyle $io;
    private ?string $projectRoot = null;
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
            ->addOption('alter', null, InputOption::VALUE_OPTIONAL, 'Table to alter')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->io->title('Create Migration');

        $migrationNameValue = $input->getArgument('name');
        if (! is_string($migrationNameValue) || trim($migrationNameValue) === '') {
            $this->io->error('The migration name must be a non-empty string.');

            return Command::FAILURE;
        }
        $this->migrationName = $migrationNameValue;

        $tableOption = $input->getOption('table');
        $this->table = is_string($tableOption) ? $tableOption : null;
        $alterOption = $input->getOption('alter');
        $this->alter = is_string($alterOption) ? $alterOption : null;

        if (! $this->initializeProjectRoot()) {
            return Command::FAILURE;
        }

        if (! $this->ensureMigrationsDirectory()) {
            return Command::FAILURE;
        }

        if (! $this->createMigrationFile()) {
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function initializeProjectRoot(): bool
    {
        $this->projectRoot = $this->findProjectRoot();
        if ($this->projectRoot === null) {
            $this->io->error('Could not find project root');

            return false;
        }

        return true;
    }

    private function ensureMigrationsDirectory(): bool
    {
        $this->migrationsPath = $this->getMigrationsPath();

        if (! is_dir($this->migrationsPath) && ! mkdir($this->migrationsPath, 0755, true)) {
            $this->io->error("Failed to create migrations directory: {$this->migrationsPath}");

            return false;
        }

        return true;
    }

    private function createMigrationFile(): bool
    {
        $fileName = $this->generateFileName();
        $filePath = $this->migrationsPath.'/'.$fileName;
        $stub = $this->generateMigrationStub();

        if (file_put_contents($filePath, $stub) === false) {
            $this->io->error('Failed to create migration file');

            return false;
        }

        $relativePath = str_replace($this->projectRoot.'/', '', $this->migrationsPath);
        $this->io->success("Migration created: {$relativePath}/{$fileName}");

        return true;
    }

    private function generateFileName(): string
    {
        $convention = $this->getNamingConvention();

        return match ($convention) {
            'sequential' => $this->generateSequentialFileName(),
            'timestamp' => $this->generateTimestampFileName(),
            default => $this->generateTimestampFileName(),
        };
    }

    private function generateTimestampFileName(): string
    {
        $timezone = $this->getTimezone();
        $timestamp = Carbon::now($timezone)->format('Y_m_d_His');

        return "{$timestamp}_{$this->migrationName}.php";
    }

    private function generateSequentialFileName(): string
    {
        $files = glob($this->migrationsPath.'/*.php');
        if ($files === false) {
            $files = []; // Default to empty array on error
        }
        $nextNumber = count($files) + 1;
        $paddedNumber = str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);

        return "{$paddedNumber}_{$this->migrationName}.php";
    }

    private function generateMigrationStub(): string
    {
        if ($this->table !== null) {
            return $this->getCreateStub();
        }

        if ($this->alter !== null) {
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

return new class() {
    /**
     * @return PromiseInterface<int|null>
     */
    public function up(SchemaBuilder \$schema): PromiseInterface
    {
        return \$schema->create('{$this->table}', function (Blueprint \$table) {
            \$table->id();
            \$table->timestamps();
        });
    }
    
    /**
     * @return PromiseInterface<int>
     */
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

return new class () {
    /**
     * @return PromiseInterface<int|null>
     */
    public function up(SchemaBuilder \$schema): PromiseInterface
    {
        return \$schema->table('{$this->alter}', function (Blueprint \$table) {
            // Add columns, indexes, etc.
        });
    }

    /**
     * @return PromiseInterface<int>
     */
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

return new class () {
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
        $currentDir = getcwd();
        $dir = ($currentDir !== false) ? $currentDir : __DIR__;

        for ($i = 0; $i < 10; $i++) {
            if (file_exists($dir.'/composer.json')) {
                return $dir;
            }
            $parent = dirname($dir);
            if ($parent === $dir) {
                break;
            }
            $dir = $parent;
        }

        return null;
    }
}