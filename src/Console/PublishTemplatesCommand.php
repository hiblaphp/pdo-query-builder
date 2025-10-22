<?php

declare(strict_types=1);

namespace Hibla\PdoQueryBuilder\Console;

use Rcalicdan\ConfigLoader\Config;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class PublishTemplatesCommand extends Command
{
    private SymfonyStyle $io;
    private ?string $projectRoot = null;
    private bool $force;

    protected function configure(): void
    {
        $this
            ->setName('publish:templates')
            ->setDescription('Publish pagination templates to the configured location')
            ->setHelp('Publishes pagination templates to the path specified in config/pdo-query-builder.php')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Overwrite existing templates')
            ->addOption('path', 'p', InputOption::VALUE_REQUIRED, 'Custom path to publish templates (overrides config)')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->force = (bool) $input->getOption('force');

        $this->io->title('Publish Pagination Templates');

        $this->projectRoot = $this->findProjectRoot();
        if ($this->projectRoot === null) {
            $this->io->error('Could not find project root');
            return Command::FAILURE;
        }

        $customPath = $input->getOption('path');
        $targetPath = is_string($customPath) && $customPath !== ''
            ? $this->resolveCustomPath($customPath)
            : $this->getConfiguredPath();

        if ($targetPath === null) {
            $this->io->error('No templates path configured. Please set pagination.templates_path in config/pdo-query-builder.php');
            $this->io->note('Example: \'templates_path\' => __DIR__ . \'/../resources/views/pagination\'');
            return Command::FAILURE;
        }

        $this->io->info("Publishing templates to: {$targetPath}");

        if (!$this->ensureDirectoryExists($targetPath)) {
            return Command::FAILURE;
        }

        $published = $this->publishTemplates($targetPath);

        if ($published > 0) {
            $this->io->success("✓ Published {$published} template(s) successfully!");
            $this->showNextSteps($targetPath);
        }

        return Command::SUCCESS;
    }

    /**
     * Get configured templates path from config file
     */
    private function getConfiguredPath(): ?string
    {
        try {
            $dbConfig = Config::get('pdo-query-builder');

            if (!is_array($dbConfig)) {
                return null;
            }

            $paginationConfig = $dbConfig['pagination'] ?? [];
            if (!is_array($paginationConfig)) {
                return null;
            }

            $templatesPath = $paginationConfig['templates_path'] ?? null;

            if (!is_string($templatesPath)) {
                return null;
            }

            if (trim($templatesPath) === '') {
                return null;
            }

            return $templatesPath;
        } catch (\Throwable $e) {
            $this->io->warning("Could not load config: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Resolve custom path from command option
     */
    private function resolveCustomPath(string $path): string
    {
        if ($this->isAbsolutePath($path)) {
            return $path;
        }

        return $this->projectRoot . DIRECTORY_SEPARATOR . ltrim($path, '/\\');
    }

    /**
     * Check if path is absolute
     */
    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/') || preg_match('/^[a-zA-Z]:[\\\\\/]/', $path) === 1;
    }

    /**
     * Ensure target directory exists
     */
    private function ensureDirectoryExists(string $path): bool
    {
        if (is_dir($path)) {
            return true;
        }

        if (!mkdir($path, 0755, true)) {
            $this->io->error("Failed to create directory: {$path}");
            return false;
        }

        $this->io->info("✓ Created directory: {$path}");
        return true;
    }

    /**
     * Publish templates to target path
     */
    private function publishTemplates(string $targetPath): int
    {
        $sourceTemplatesDir = $this->getSourceTemplatesPath();

        if (!is_dir($sourceTemplatesDir)) {
            $this->io->error("Source templates directory not found: {$sourceTemplatesDir}");
            $this->io->note('Attempted paths for debugging:');
            $this->debugTemplatePaths();
            return 0;
        }

        $templates = [
            'bootstrap.php',
            'tailwind.php',
            'simple.php',
            'cursor-simple.php',
            'cursor-bootstrap.php',
            'cursor-tailwind.php'
        ];

        $copiedCount = 0;

        $this->io->section('Publishing Templates:');

        foreach ($templates as $template) {
            $source = $sourceTemplatesDir . DIRECTORY_SEPARATOR . $template;
            $destination = $targetPath . DIRECTORY_SEPARATOR . $template;

            if (!file_exists($source)) {
                $this->io->warning("Source not found: {$template}");
                continue;
            }

            if (file_exists($destination) && !$this->force) {
                if (!$this->io->confirm("  Template '{$template}' already exists. Overwrite?", false)) {
                    $this->io->text("  <comment>⊘</comment> Skipped: {$template}");
                    continue;
                }
            }

            if (copy($source, $destination)) {
                $copiedCount++;
                $this->io->text("  <info>✓</info> Published: {$template}");
            } else {
                $this->io->text("  <error>✗</error> Failed: {$template}");
            }
        }

        return $copiedCount;
    }

    /**
     * Show next steps after publishing
     */
    private function showNextSteps(string $targetPath): void
    {
        $this->io->section('Next Steps:');

        $this->io->listing([
            'Templates have been published to: ' . $targetPath,
            'Customize the templates to fit your design needs',
            'Templates will be automatically loaded from this location',
        ]);

        $this->io->note([
            'Available templates:',
            '  - bootstrap.php     → Bootstrap 5 styled pagination',
            '  - tailwind.php      → Tailwind CSS styled pagination',
            '  - simple.php        → Simple text-based pagination',
            '  - cursor-simple.php → Simple cursor-based pagination',
            '  - cursor-bootstrap.php → Bootstrap 5 styled cursor pagination',
            '  - cursor-tailwind.php → Tailwind CSS styled cursor pagination',
        ]);

        $this->io->text([
            '',
            'Usage in your code:',
            '  $paginator = await($builder->paginate(15));',
            '  echo $paginator->render(\'bootstrap\'); // Uses your custom template',
        ]);
    }

    /**
     * Get source templates path from vendor directory
     * FIXED: Better path resolution for Windows/Unix compatibility
     */
    private function getSourceTemplatesPath(): string
    {
        $paths = [
            $this->projectRoot . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'hibla' . DIRECTORY_SEPARATOR . 'pdo-query-builder' . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Pagination' . DIRECTORY_SEPARATOR . 'templates',

            dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Pagination' . DIRECTORY_SEPARATOR . 'templates',

            dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'Pagination' . DIRECTORY_SEPARATOR . 'templates',

            __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'Pagination' . DIRECTORY_SEPARATOR . 'templates',
        ];

        foreach ($paths as $path) {
            $normalizedPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
            if (is_dir($normalizedPath)) {
                return $normalizedPath;
            }
        }

        return $paths[0];
    }

    /**
     * Debug helper to show all attempted template paths
     */
    private function debugTemplatePaths(): void
    {
        $paths = [
            'Project vendor' => $this->projectRoot . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'hibla' . DIRECTORY_SEPARATOR . 'pdo-query-builder' . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Pagination' . DIRECTORY_SEPARATOR . 'templates',
            'Package development' => dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Pagination' . DIRECTORY_SEPARATOR . 'templates',
            'Alternative location' => dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'Pagination' . DIRECTORY_SEPARATOR . 'templates',
            'Relative path' => __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'Pagination' . DIRECTORY_SEPARATOR . 'templates',
        ];

        foreach ($paths as $label => $path) {
            $normalizedPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
            $exists = is_dir($normalizedPath) ? '✓ EXISTS' : '✗ NOT FOUND';
            $this->io->text("  {$exists} [{$label}]: {$normalizedPath}");
        }
    }

    /**
     * Find project root directory
     */
    private function findProjectRoot(): ?string
    {
        $currentDir = getcwd();
        $dir = ($currentDir !== false) ? $currentDir : __DIR__;

        for ($i = 0; $i < 10; $i++) {
            if (file_exists($dir . DIRECTORY_SEPARATOR . 'composer.json')) {
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
