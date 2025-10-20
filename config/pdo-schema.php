<?php

return [
    'migrations_path' => $_ENV['MIGRATIONS_PATH'] ?? __DIR__.'/../database/migrations',
    'migrations_table' => $_ENV['MIGRATIONS_TABLE'] ?? 'migrations',
    'naming_convention' => $_ENV['MIGRATION_NAMING'] ?? 'timestamp',
];
