<?php

require 'vendor/autoload.php';

use function Rcalicdan\ConfigLoader\env;

return [
    /*
    |--------------------------------------------------------------------------
    | Migrations Path
    |--------------------------------------------------------------------------
    |
    | The directory where migration files are stored and loaded from.
    | You can use nested directories to organize migrations.
    |
    */
    'migrations_path' => __DIR__ . '/../database/migrations',

    /*
    |--------------------------------------------------------------------------
    | Migrations Table
    |--------------------------------------------------------------------------
    |
    | The database table name used to track which migrations have been run.
    |
    */
    'migrations_table' => 'migrations',

    /*
    |--------------------------------------------------------------------------
    | Naming Convention
    |--------------------------------------------------------------------------
    |
    | The naming convention for generated migration files.
    | Supported values: "timestamp", "sequential"
    |
    */
    'naming_convention' => 'timestamp',

    /*
    |--------------------------------------------------------------------------
    | Timezone
    |--------------------------------------------------------------------------
    |
    | The timezone used for timestamp-based migration file names and timestamp columns.
    |
    */
    'timezone' => env('TIMEZONE', 'UTC'),

    /*
    |--------------------------------------------------------------------------
    | Recursive Migration Discovery
    |--------------------------------------------------------------------------
    |
    | When enabled, the migration system will scan subdirectories recursively
    | for migration files. This allows you to organize migrations into folders.
    |
    */
    'recursive' => true,

    /*
    |--------------------------------------------------------------------------
    | Connection-Specific Migration Paths
    |--------------------------------------------------------------------------
    |
    | Define specific subdirectories for different database connections.
    | When a migration is created with --connection flag, it will be placed
    | in the corresponding subdirectory if defined here.
    |
    | Example: 'mysql' => 'mysql', 'pgsql' => 'postgres'
    |
    */
    'connection_paths' => [
        // 'mysql' => 'mysql',
        // 'pgsql' => 'postgres',
        // 'sqlite' => 'sqlite',
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    |
    | The database connections to use for migrations.
    |
    | Connection-specific overrides (optional)
    |
    */
    'connections' => []
];