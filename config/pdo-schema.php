<?php

return [
    'schema_path' => $_ENV['SCHEMA_PATH'] ?? __DIR__ . '/../database/schema',
    'migrations_path' => $_ENV['MIGRATIONS_PATH'] ?? __DIR__ . '/../database/migrations',
    'migrations_table' => $_ENV['MIGRATIONS_TABLE'] ?? 'migrations',
    'naming_convention' => $_ENV['MIGRATION_NAMING'] ?? 'timestamp',
    'auto_migrate' => filter_var($_ENV['AUTO_MIGRATE'] ?? false, FILTER_VALIDATE_BOOLEAN),
];