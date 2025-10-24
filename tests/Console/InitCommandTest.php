<?php

use Hibla\PdoQueryBuilder\Console\InitCommand;
use Tests\Helpers\ConsoleTestHelper;
use Symfony\Component\Console\Command\Command;

beforeEach(function () {
    ConsoleTestHelper::init();
    ConsoleTestHelper::createTestDirectory();
    ConsoleTestHelper::setupMockProjectRoot();
    ConsoleTestHelper::changeToTestDirectory();
    ConsoleTestHelper::setupCommand(InitCommand::class, 'init');
});

afterEach(function () {
    ConsoleTestHelper::cleanup();
});

test('init command creates config directory', function () {
    ConsoleTestHelper::executeCommand();
    
    expect(ConsoleTestHelper::assertDirectoryExists('/config'))->toBeTrue();
});

test('init command copies configuration files', function () {
    ConsoleTestHelper::setupMockSourceConfigs();
    
    $exitCode = ConsoleTestHelper::executeCommand();
    
    expect($exitCode)->toBe(Command::SUCCESS);
    expect(ConsoleTestHelper::assertFileExists('/config/pdo-query-builder.php'))->toBeTrue();
    expect(ConsoleTestHelper::assertFileExists('/config/pdo-schema.php'))->toBeTrue();
});

test('init command creates async-pdo executable', function () {
    ConsoleTestHelper::setupMockSourceConfigs();
    
    ConsoleTestHelper::executeCommand();
    
    expect(ConsoleTestHelper::assertFileExists('/async-pdo'))->toBeTrue();
    expect(ConsoleTestHelper::assertFileContains('/async-pdo', '#!/usr/bin/env php'))->toBeTrue();
    expect(ConsoleTestHelper::assertFileContains('/async-pdo', 'Async PDO Query Builder'))->toBeTrue();
});

test('init command sets executable permissions on unix systems', function () {
    skipIfWindows();
    
    ConsoleTestHelper::setupMockSourceConfigs();
    ConsoleTestHelper::executeCommand();
    
    expect(ConsoleTestHelper::assertFileIsExecutable('/async-pdo'))->toBeTrue();
});

test('init command displays success message', function () {
    ConsoleTestHelper::setupMockSourceConfigs();
    
    $exitCode = ConsoleTestHelper::executeCommand();
    
    expect($exitCode)->toBe(Command::SUCCESS);
    expect(ConsoleTestHelper::assertOutputContains('Configuration created'))->toBeTrue();
    expect(ConsoleTestHelper::assertOutputContains('Created async-pdo executable'))->toBeTrue();
});

test('init command prompts for overwrite when config exists', function () {
    ConsoleTestHelper::setupMockSourceConfigs();
    ConsoleTestHelper::createDirectory('/config');
    ConsoleTestHelper::createFile('/config/pdo-query-builder.php', '<?php // existing');
    
    ConsoleTestHelper::executeCommand([], ['no', 'no']);
    
    expect(ConsoleTestHelper::assertOutputContains('already exists'))->toBeTrue();
});

test('init command overwrites config with force option', function () {
    ConsoleTestHelper::setupMockSourceConfigs([
        'pdo-query-builder.php' => '<?php return [\'driver\' => \'mysql\'];'
    ]);
    
    ConsoleTestHelper::createDirectory('/config');
    ConsoleTestHelper::createFile('/config/pdo-query-builder.php', '<?php // old content');
    
    ConsoleTestHelper::executeCommand(['--force' => true]);
    
    expect(ConsoleTestHelper::assertFileContains('/config/pdo-query-builder.php', 'driver'))->toBeTrue();
    expect(ConsoleTestHelper::assertFileContains('/config/pdo-query-builder.php', 'mysql'))->toBeTrue();
    expect(ConsoleTestHelper::assertFileNotContains('/config/pdo-query-builder.php', 'old content'))->toBeTrue();
});

test('init command displays usage instructions', function () {
    ConsoleTestHelper::setupMockSourceConfigs();
    ConsoleTestHelper::executeCommand();
    
    $usageCommands = [
        'Usage:',
        'php async-pdo init',
        'php async-pdo migrate',
        'php async-pdo make:migration',
        'php async-pdo migrate:rollback',
    ];
    
    foreach ($usageCommands as $command) {
        expect(ConsoleTestHelper::assertOutputContains($command))->toBeTrue();
    }
});

test('init command displays env file instructions when env does not exist', function () {
    ConsoleTestHelper::setupMockSourceConfigs();
    ConsoleTestHelper::executeCommand();
    
    $envVars = ['Create .env file', 'DB_CONNECTION', 'DB_HOST', 'DB_DATABASE'];
    
    foreach ($envVars as $var) {
        expect(ConsoleTestHelper::assertOutputContains($var))->toBeTrue();
    }
});

test('init command does not display env instructions when env exists', function () {
    ConsoleTestHelper::setupMockSourceConfigs();
    ConsoleTestHelper::createEnvFile(['DB_CONNECTION' => 'mysql']);
    
    ConsoleTestHelper::executeCommand();
    
    expect(ConsoleTestHelper::assertOutputNotContains('Create .env file'))->toBeTrue();
});

test('init command returns failure when project root cannot be found', function () {
    ConsoleTestHelper::cleanup();
    ConsoleTestHelper::init();
    ConsoleTestHelper::createIsolatedTestDirectory();
    ConsoleTestHelper::changeToTestDirectory();
    ConsoleTestHelper::setupCommand(InitCommand::class, 'init');
    
    $exitCode = ConsoleTestHelper::executeCommand();
    
    expect($exitCode)->toBe(Command::FAILURE);
    expect(ConsoleTestHelper::assertOutputContains('Could not find project root'))->toBeTrue();
});

test('async-pdo executable contains all required commands', function () {
    ConsoleTestHelper::setupMockSourceConfigs();
    ConsoleTestHelper::executeCommand();
    
    $expectedCommands = [
        'InitCommand',
        'PublishTemplatesCommand',
        'MakeMigrationCommand',
        'MigrateCommand',
        'MigrateRollbackCommand',
        'MigrateResetCommand',
        'MigrateRefreshCommand',
        'MigrateFreshCommand',
        'MigrateStatusCommand',
        'StatusCommand',
    ];
    
    foreach ($expectedCommands as $command) {
        expect(ConsoleTestHelper::assertFileContains('/async-pdo', $command))->toBeTrue();
    }
});

test('init command handles multiple file operations', function () {
    ConsoleTestHelper::setupMockSourceConfigs();
    
    ConsoleTestHelper::createDirectories(['/config', '/database', '/storage']);
    
    ConsoleTestHelper::createFiles([
        '/config/app.php' => '<?php return [];',
        '/config/database.php' => '<?php return [];',
    ]);
    
    expect(ConsoleTestHelper::assertDirectoryExists('/config'))->toBeTrue();
    expect(ConsoleTestHelper::assertDirectoryExists('/database'))->toBeTrue();
    expect(count(ConsoleTestHelper::getFilesInDirectory('/config')))->toBe(2);
});

test('init command with existing async-pdo without force option', function () {
    ConsoleTestHelper::setupMockSourceConfigs();
    ConsoleTestHelper::createFile('/async-pdo', '<?php // old version');
    
    ConsoleTestHelper::executeCommand();
    
    expect(ConsoleTestHelper::assertOutputContains('async-pdo file already exists'))->toBeTrue();
    expect(ConsoleTestHelper::assertFileContains('/async-pdo', 'old version'))->toBeTrue();
});

test('init command overwrites async-pdo with force option', function () {
    ConsoleTestHelper::setupMockSourceConfigs();
    ConsoleTestHelper::createFile('/async-pdo', '<?php // old version');
    
    ConsoleTestHelper::executeCommand(['--force' => true]);
    
    expect(ConsoleTestHelper::assertFileContains('/async-pdo', 'Async PDO Query Builder'))->toBeTrue();
    expect(ConsoleTestHelper::assertFileNotContains('/async-pdo', 'old version'))->toBeTrue();
});

test('can get full path from helper', function () {
    $path = ConsoleTestHelper::path('/config');
    
    expect($path)->toContain('pdo-query-builder-test');
    expect($path)->toEndWith('/config');
});