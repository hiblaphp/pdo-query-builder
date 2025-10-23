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
    private ?string $connection = null;

    protected function configure(): void
    {
        $this
            ->setName('make:migration')
            ->setDescription('Create a new migration file')
            ->addArgument('name', InputArgument::REQUIRED, 'Migration name')
            ->addOption('table', null, InputOption::VALUE_OPTIONAL, 'Table to create')
            ->addOption('alter', null, InputOption::VALUE_OPTIONAL, 'Table to alter')
            ->addOption('connection', null, InputOption::VALUE_OPTIONAL, 'The database connection to use')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->io->title('Create Migration');

        $connectionOption = $input->getOption('connection');
        $this->connection = (is_string($connectionOption) && $connectionOption !== '') ? $connectionOption : null;

        if ($this->connection !== null) {
            $this->io->note("Using database connection: {$this->connection}");
        }

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
        $this->migrationsPath = $this->getMigrationsPath($this->connection);

        if (! is_dir($this->migrationsPath) && ! mkdir($this->migrationsPath, 0755, true)) {
            $this->io->error("Failed to create migrations directory: {$this->migrationsPath}");

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
            $this->io->error('Failed to create migration file');

            return false;
        }

        $relativePath = str_replace($this->projectRoot . '/', '', $this->migrationsPath);
        $this->io->success("Migration created: {$relativePath}/{$fileName}");

        return true;
    }

    private function generateFileName(): string
    {
        $convention = $this->getNamingConvention($this->connection);

        return match ($convention) {
            'sequential' => $this->generateSequentialFileName(),
            'timestamp' => $this->generateTimestampFileName(),
            default => $this->generateTimestampFileName(),
        };
    }

    private function generateTimestampFileName(): string
    {
        $timezone = $this->getTimezone($this->connection);
        $timestamp = Carbon::now($timezone)->format('Y_m_d_His');

        return "{$timestamp}_{$this->migrationName}.php";
    }

    private function generateSequentialFileName(): string
    {
        $files = glob($this->migrationsPath . '/*.php');
        if ($files === false) {
            $files = [];
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
        $connectionLine = $this->connection !== null
            ? "    protected ?string \$connection = '{$this->connection}';\n\n"
            : '';

        return "<?php

use Hibla\PdoQueryBuilder\Schema\Blueprint;
use Hibla\PdoQueryBuilder\Schema\Migration;
use Hibla\Promise\Interfaces\PromiseInterface;

return new class extends Migration
{
{$connectionLine}    /**
     * Run the migration.
     *
     * @return PromiseInterface<int|null>
     */
    public function up(): PromiseInterface
    {
        return \$this->create('{$this->table}', function (Blueprint \$table) {
            \$table->id();
            \$table->timestamps();
        });
    }
    
    /**
     * Reverse the migration.
     *
     * @return PromiseInterface<int>
     */
    public function down(): PromiseInterface
    {
        return \$this->dropIfExists('{$this->table}');
    }
};
";
    }

    private function getAlterStub(): string
    {
        $connectionLine = $this->connection !== null
            ? "    protected ?string \$connection = '{$this->connection}';\n\n"
            : '';

        return "<?php

use Hibla\PdoQueryBuilder\Schema\Blueprint;
use Hibla\PdoQueryBuilder\Schema\Migration;
use Hibla\Promise\Interfaces\PromiseInterface;

return new class extends Migration
{
{$connectionLine}    /**
     * Run the migration.
     *
     * @return PromiseInterface<int|null>
     */
    public function up(): PromiseInterface
    {
        return \$this->table('{$this->alter}', function (Blueprint \$table) {
            // Add columns, indexes, etc.
        });
    }

    /**
     * Reverse the migration.
     *
     * @return PromiseInterface<int|null>
     */
    public function down(): PromiseInterface
    {
        return \$this->table('{$this->alter}', function (Blueprint \$table) {
            // Reverse the changes
        });
    }
};
";
    }

    private function getBlankStub(): string
    {
        $connectionLine = $this->connection !== null
            ? "    protected ?string \$connection = '{$this->connection}';\n\n"
            : '';

        return "<?php

use Hibla\PdoQueryBuilder\Schema\Blueprint;
use Hibla\PdoQueryBuilder\Schema\Migration;
use Hibla\Promise\Interfaces\PromiseInterface;

return new class extends Migration
{
{$connectionLine}    /**
     * Run the migration.
     *
     * @return PromiseInterface<mixed>
     */
    public function up(): PromiseInterface
    {
        // Write your migration here
    }

    /**
     * Reverse the migration.
     *
     * @return PromiseInterface<mixed>
     */
    public function down(): PromiseInterface
    {
        // Reverse your migration here
    }
};
";
    }

    private function findProjectRoot(): ?string
    {
        $currentDir = getcwd();
        $dir = ($currentDir !== false) ? $currentDir : __DIR__;

        for ($i = 0; $i < 10; $i++) {
            if (file_exists($dir . '/composer.json')) {
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