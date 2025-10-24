<?php

namespace Tests\Helpers;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class ConsoleTestHelper
{
    private static ?string $testDir = null;
    private static ?string $originalDir = null;
    private static ?CommandTester $commandTester = null;
    private static ?Command $command = null;

    public static function init(): void
    {
        self::$originalDir = getcwd();
        self::$commandTester = null;
        self::$command = null;
    }

    public static function createTestDirectory(): string
    {
        self::$testDir = sys_get_temp_dir() . '/pdo-query-builder-test-' . uniqid();
        mkdir(self::$testDir, 0755, true);

        return self::$testDir;
    }

    public static function createIsolatedTestDirectory(): string
    {
        $basePath = sys_get_temp_dir() . '/isolated-test-' . uniqid();
        $deepPath = $basePath . '/a/b/c/d/e/f/g/h/i/j/k';
        mkdir($deepPath, 0755, true);
        self::$testDir = $deepPath;

        return self::$testDir;
    }

    public static function setupMockProjectRoot(): void
    {
        self::createDirectory('/vendor');
        self::createFile('/vendor/autoload.php', '<?php');
        self::createFile('/composer.json', json_encode([
            'name' => 'test/project',
            'require' => []
        ], JSON_PRETTY_PRINT));
    }

    public static function setupMockSourceConfigs(array $configs = []): void
    {
        $defaultConfigs = [
            'pdo-query-builder.php' => '<?php return [\'driver\' => \'mysql\'];',
            'pdo-schema.php' => '<?php return [\'migrations\' => \'database/migrations\'];',
        ];

        $configs = array_merge($defaultConfigs, $configs);

        $vendorConfigDir = '/vendor/hibla/pdo-query-builder/config';
        self::createDirectory($vendorConfigDir);

        foreach ($configs as $filename => $content) {
            self::createFile($vendorConfigDir . '/' . $filename, $content);
        }
    }

    public static function createDirectory(string $path): string
    {
        self::ensureTestDirExists();
        $fullPath = self::$testDir . $path;

        if (!is_dir($fullPath)) {
            mkdir($fullPath, 0755, true);
        }

        return $fullPath;
    }

    public static function createFile(string $path, string $content = ''): string
    {
        self::ensureTestDirExists();
        $fullPath = self::$testDir . $path;
        $dir = dirname($fullPath);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($fullPath, $content);

        return $fullPath;
    }

    public static function fileExists(string $path): bool
    {
        self::ensureTestDirExists();
        return file_exists(self::$testDir . $path);
    }

    public static function getFileContents(string $path): string|false
    {
        self::ensureTestDirExists();

        return file_exists(self::$testDir . $path)
            ? file_get_contents(self::$testDir . $path)
            : false;
    }

    public static function setupCommand(string $commandClass, ?string $commandName = null): CommandTester
    {
        $application = new Application();
        self::$command = new $commandClass();
        $application->add(self::$command);

        $commandName = $commandName ?? self::$command->getName();
        self::$command = $application->find($commandName);
        self::$commandTester = new CommandTester(self::$command);

        return self::$commandTester;
    }

    public static function executeCommand(array $options = [], array $inputs = []): int
    {
        if (self::$commandTester === null) {
            throw new \RuntimeException('Command not setup. Call setupCommand() first.');
        }

        if (!empty($inputs)) {
            self::$commandTester->setInputs($inputs);
        }

        return self::$commandTester->execute($options);
    }

    public static function getOutput(): string
    {
        if (self::$commandTester === null) {
            throw new \RuntimeException('Command not setup. Call setupCommand() first.');
        }

        return self::$commandTester->getDisplay();
    }

    public static function getStatusCode(): int
    {
        if (self::$commandTester === null) {
            throw new \RuntimeException('Command not setup. Call setupCommand() first.');
        }

        return self::$commandTester->getStatusCode();
    }

    public static function getCommandTester(): ?CommandTester
    {
        return self::$commandTester;
    }

    public static function changeToTestDirectory(): void
    {
        self::ensureTestDirExists();
        chdir(self::$testDir);
    }

    public static function restoreOriginalDirectory(): void
    {
        if (self::$originalDir !== null) {
            chdir(self::$originalDir);
        }
    }

    public static function getTestDir(): string
    {
        self::ensureTestDirExists();
        return self::$testDir;
    }

    public static function cleanup(): void
    {
        self::restoreOriginalDirectory();

        if (self::$testDir !== null && file_exists(self::$testDir)) {
            $parts = explode('/', str_replace('\\', '/', self::$testDir));
            $rootTestDir = '';
            foreach ($parts as $part) {
                if (str_contains($part, 'isolated-test-') || str_contains($part, 'pdo-query-builder-test-')) {
                    $rootTestDir .= '/' . $part;
                    break;
                }
                $rootTestDir .= '/' . $part;
            }

            if (!empty($rootTestDir) && file_exists($rootTestDir)) {
                self::deleteDirectory($rootTestDir);
            } elseif (file_exists(self::$testDir)) {
                self::deleteDirectory(self::$testDir);
            }
        }

        self::reset();
    }

    public static function reset(): void
    {
        self::$testDir = null;
        self::$commandTester = null;
        self::$command = null;
    }

    private static function deleteDirectory(string $dir): void
    {
        if (!file_exists($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? self::deleteDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    public static function assertFileExists(string $path): bool
    {
        return self::fileExists($path);
    }

    public static function assertFileContains(string $path, string $text): bool
    {
        $content = self::getFileContents($path);
        return $content !== false && str_contains($content, $text);
    }

    public static function assertFileNotContains(string $path, string $text): bool
    {
        $content = self::getFileContents($path);
        return $content !== false && !str_contains($content, $text);
    }

    public static function assertOutputContains(string $text): bool
    {
        return str_contains(self::getOutput(), $text);
    }

    public static function assertOutputNotContains(string $text): bool
    {
        return !str_contains(self::getOutput(), $text);
    }

    public static function createEnvFile(array $variables = []): void
    {
        $content = '';
        foreach ($variables as $key => $value) {
            $content .= "{$key}={$value}\n";
        }
        self::createFile('/.env', $content);
    }

    public static function getFilePermissions(string $path): int|false
    {
        self::ensureTestDirExists();
        $fullPath = self::$testDir . $path;

        return file_exists($fullPath) ? fileperms($fullPath) : false;
    }

    public static function assertFileIsExecutable(string $path): bool
    {
        $permissions = self::getFilePermissions($path);
        return $permissions !== false && ($permissions & 0111) !== 0;
    }

    public static function deleteFile(string $path): bool
    {
        self::ensureTestDirExists();
        $fullPath = self::$testDir . $path;

        if (file_exists($fullPath)) {
            return unlink($fullPath);
        }

        return false;
    }

    public static function path(string $relativePath = ''): string
    {
        self::ensureTestDirExists();
        return self::$testDir . $relativePath;
    }

    private static function ensureTestDirExists(): void
    {
        if (self::$testDir === null) {
            throw new \RuntimeException('Test directory not created. Call createTestDirectory() first.');
        }
    }

    public static function copyFile(string $source, string $destination): bool
    {
        self::ensureTestDirExists();
        $sourcePath = self::$testDir . $source;
        $destPath = self::$testDir . $destination;

        if (!file_exists($sourcePath)) {
            return false;
        }

        $destDir = dirname($destPath);
        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }

        return copy($sourcePath, $destPath);
    }

    public static function createFiles(array $files): void
    {
        foreach ($files as $path => $content) {
            self::createFile($path, $content);
        }
    }

    public static function createDirectories(array $directories): void
    {
        foreach ($directories as $directory) {
            self::createDirectory($directory);
        }
    }

    public static function getFilesInDirectory(string $path): array
    {
        self::ensureTestDirExists();
        $fullPath = self::$testDir . $path;

        if (!is_dir($fullPath)) {
            return [];
        }

        return array_values(array_diff(scandir($fullPath), ['.', '..']));
    }

    public static function assertDirectoryExists(string $path): bool
    {
        self::ensureTestDirExists();
        return is_dir(self::$testDir . $path);
    }

    public static function assertDirectoryIsEmpty(string $path): bool
    {
        return count(self::getFilesInDirectory($path)) === 0;
    }
}
