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
