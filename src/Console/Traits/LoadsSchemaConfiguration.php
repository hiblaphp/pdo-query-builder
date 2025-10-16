<?php

declare(strict_types=1);

namespace Hibla\PdoQueryBuilder\Console\Traits;

use Hibla\PdoQueryBuilder\Utilities\ConfigLoader;

trait LoadsSchemaConfiguration
{
    private function getSchemaConfig(): array
    {
        try {
            $configLoader = ConfigLoader::getInstance();
            $config = $configLoader->get('pdo-schema');
            
            if (!is_array($config)) {
                return $this->getDefaultSchemaConfig();
            }
            
            return $config;
        } catch (\Throwable $e) {
            return $this->getDefaultSchemaConfig();
        }
    }
    
    private function getDefaultSchemaConfig(): array
    {
        return [
            'schema_path' => $this->projectRoot . '/database/schema',
            'migrations_path' => $this->projectRoot . '/database/migrations',
            'migrations_table' => 'migrations',
            'naming_convention' => 'timestamp',
            'auto_migrate' => false,
        ];
    }
    
    private function getMigrationsPath(): string
    {
        $config = $this->getSchemaConfig();
        $path = $config['migrations_path'] ?? $this->projectRoot . '/database/migrations';
        
        if (!$this->isAbsolutePath($path)) {
            $path = $this->projectRoot . '/' . ltrim($path, '/');
        }
        
        return $path;
    }
    
    private function getMigrationsTable(): string
    {
        $config = $this->getSchemaConfig();
        return $config['migrations_table'] ?? 'migrations';
    }
    
    private function getNamingConvention(): string
    {
        $config = $this->getSchemaConfig();
        return $config['naming_convention'] ?? 'timestamp';
    }
    
    private function isAbsolutePath(string $path): bool
    {
        return $path[0] === '/' || preg_match('/^[a-zA-Z]:[\\\\\/]/', $path);
    }
}