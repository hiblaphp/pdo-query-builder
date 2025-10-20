<?php

declare(strict_types=1);

namespace Hibla\PdoQueryBuilder\Console\Traits;

use Hibla\PdoQueryBuilder\Utilities\ConfigLoader;

trait LoadsSchemaConfiguration
{
    /**
     * @return array{
     *     migrations_path: string,
     *     migrations_table: string,
     *     naming_convention: string,
     *     timezone: string
     * }
     */
    private function getSchemaConfig(): array
    {
        $defaults = $this->getDefaultSchemaConfig();
        $loadedConfig = [];

        try {
            $configLoader = ConfigLoader::getInstance();
            $config = $configLoader->get('pdo-schema');

            if (is_array($config)) {
                $loadedConfig = $config;
            }
        } catch (\Throwable $e) {
            // Ignore exceptions and use defaults
        }

        $finalConfig = array_merge($defaults, $loadedConfig);

        return [
            'migrations_path' => is_string($finalConfig['migrations_path']) ? $finalConfig['migrations_path'] : $defaults['migrations_path'],
            'migrations_table' => is_string($finalConfig['migrations_table']) ? $finalConfig['migrations_table'] : $defaults['migrations_table'],
            'naming_convention' => is_string($finalConfig['naming_convention']) ? $finalConfig['naming_convention'] : $defaults['naming_convention'],
            'timezone' => is_string($finalConfig['timezone'] ?? null) ? $finalConfig['timezone'] : $defaults['timezone'],
        ];
    }

    /**
     * @return array{
     *     schema_path: string,
     *     migrations_path: string,
     *     migrations_table: string,
     *     naming_convention: string,
     *     timezone: string,
     *     auto_migrate: bool
     * }
     */
    private function getDefaultSchemaConfig(): array
    {
        // @phpstan-ignore-next-line
        $projectRoot = $this->projectRoot ?? '.';

        return [
            'schema_path' => $projectRoot.'/database/schema',
            'migrations_path' => $projectRoot.'/database/migrations',
            'migrations_table' => 'migrations',
            'naming_convention' => 'timestamp',
            'timezone' => 'UTC',
            'auto_migrate' => false,
        ];
    }

    private function getMigrationsPath(): string
    {
        $config = $this->getSchemaConfig();
        $path = $config['migrations_path'];

        // @phpstan-ignore-next-line
        $projectRoot = $this->projectRoot ?? '.';

        if (! $this->isAbsolutePath($path)) {
            $path = $projectRoot.'/'.ltrim($path, '/');
        }

        return $path;
    }

    private function getMigrationsTable(): string
    {
        $config = $this->getSchemaConfig();

        return $config['migrations_table'];
    }

    private function getNamingConvention(): string
    {
        $config = $this->getSchemaConfig();

        return $config['naming_convention'];
    }

    private function getTimezone(): string
    {
        $config = $this->getSchemaConfig();

        return $config['timezone'];
    }

    private function isAbsolutePath(string $path): bool
    {
        if ($path === '') {
            return false;
        }

        return $path[0] === '/' || preg_match('/^[a-zA-Z]:[\\\\\/]/', $path) === 1;
    }
}