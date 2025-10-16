<?php

namespace Hibla\PdoQueryBuilder\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class MakeMigrationCommand extends Command
{
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
        $io = new SymfonyStyle($input, $output);
        $io->title('Create Migration');

        $name = $input->getArgument('name');
        $table = $input->getOption('table');
        $alter = $input->getOption('alter');

        $projectRoot = $this->findProjectRoot();
        if (!$projectRoot) {
            $io->error('Could not find project root');
            return Command::FAILURE;
        }

        $migrationsPath = $projectRoot . '/database/migrations';
        if (!is_dir($migrationsPath) && !mkdir($migrationsPath, 0755, true)) {
            $io->error("Failed to create migrations directory");
            return Command::FAILURE;
        }

        $timestamp = date('Y_m_d_His');
        $className = $this->generateClassName($name);
        $fileName = "{$timestamp}_{$name}.php";
        $filePath = $migrationsPath . '/' . $fileName;

        if ($table) {
            $stub = $this->getCreateStub($className, $table);
        } elseif ($alter) {
            $stub = $this->getAlterStub($className, $alter);
        } else {
            $stub = $this->getBlankStub($className);
        }

        if (file_put_contents($filePath, $stub) === false) {
            $io->error("Failed to create migration file");
            return Command::FAILURE;
        }

        $io->success("Migration created: database/migrations/{$fileName}");

        return Command::SUCCESS;
    }

    private function generateClassName(string $name): string
    {
        return str_replace('_', '', ucwords($name, '_'));
    }

    private function getCreateStub(string $className, string $table): string
    {
        return "<?php

use Hibla\PdoQueryBuilder\Schema\Blueprint;
use Hibla\PdoQueryBuilder\Schema\SchemaBuilder;
use Hibla\Promise\Interfaces\PromiseInterface;

return new class
{
    public function up(SchemaBuilder \$schema): PromiseInterface
    {
        return \$schema->create('{$table}', function (Blueprint \$table) {
            \$table->id();
            \$table->timestamps();
        });
    }

    public function down(SchemaBuilder \$schema): PromiseInterface
    {
        return \$schema->dropIfExists('{$table}');
    }
};
";
    }

    private function getAlterStub(string $className, string $table): string
    {
        return "<?php

use Hibla\PdoQueryBuilder\Schema\Blueprint;
use Hibla\PdoQueryBuilder\Schema\SchemaBuilder;
use Hibla\Promise\Interfaces\PromiseInterface;

return new class
{
    public function up(SchemaBuilder \$schema): PromiseInterface
    {
        return \$schema->table('{$table}', function (Blueprint \$table) {
            // Add columns, indexes, etc.
        });
    }

    public function down(SchemaBuilder \$schema): PromiseInterface
    {
        return \$schema->table('{$table}', function (Blueprint \$table) {
            // Reverse the changes
        });
    }
};
";
    }

    private function getBlankStub(string $className): string
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